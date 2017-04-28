<?php

namespace App\Http\Controllers;

use App\Account;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use App\Helpers\Configuration;
use App\Helpers\MailSend;
use Illuminate\Support\Facades\Hash;

/**
 * Class AccountController
 * @package App\Http\Controllers
 */
class AccountController extends Controller
{
    /**
     * AccountController constructor.
     * @param Request $request
     * @throws \Exception
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
        if ($request->route('appName') !== 'accounts') {
            throw new \Exception('Wrong application name. Should be accounts.', 400);
        }
    }

    /**
     * Returns current user if there, otherwise HTTP 401
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function current()
    {
        $account = Auth::user();

        if (!$account) {
            return $this->jsonError('User not logged in.', 401);
        }

        return $this->jsonSuccess($account);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $accounts = Account::all();
        return $this->jsonSuccess($accounts);
    }

    /**
     * Accepts email and password, returns authentication token on success and (bool) false on failed login
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->jsonError('Invalid credentials.', 401);
        }

        JWTAuth::setToken($token);

        $account = Auth::user();

        return $this->jsonSuccess($account);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $fields = $request->all();
        $this->validateInputsForResource($fields, 'accounts', null, [], false);

        $account = Account::create($fields);

        $credentials = $request->only('email', 'password');
        if (!$token = JWTAuth::attempt($credentials)) {
            return $this->jsonError(['Issue with automatic sign in.'], 401);
        }

        JWTAuth::setToken($token);

        //send confirmation E-mail upon profile creation on the platform
        $teamSlackInfo = Configuration::getConfiguration(true);
        if ($teamSlackInfo === false) {
            $teamSlackInfo = [];
        }

        $data = [
            'name' => $account->name,
            'email' => $account->email,
            'github' => $account->github,
            'trello' => $account->trello,
            'slack' => $account->slack,
            'teamSlack' => $teamSlackInfo
        ];
        $view = 'emails.registration';
        $subject = 'Welcome to The Shop platform!';

        MailSend::send($view, $data, $account, $subject);

        return $this->jsonSuccess($account);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $account = Account::find($request->route('accounts'));
        if (!$account) {
            return $this->jsonError('Account not found.', 404);
        }
        return $this->jsonSuccess($account);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $account = Account::find($request->route('accounts'));

        if (!$account instanceof Account) {
            return $this->jsonError('Model not found.', 404);
        }

        if ($account->id !== $this->getCurrentProfile()->id && $this->getCurrentProfile()->admin !== true) {
            return $this->jsonError('Not enough permissions.', 403);
        }

        $fields = $request->all();
        $this->validateInputsForResource(
            $fields,
            'accounts',
            null,
            [
                'email' => 'required|email|unique:accounts'
            ]
        );

        $account->fill($fields);
        
        $account->save();

        return $this->jsonSuccess($account);
    }

    /**
     * Change password implementation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        $account = $this->getCurrentProfile();

        if (!$account instanceof Account) {
            return $this->jsonError('Account not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'oldPassword' => 'required|min:8',
                'newPassword' => 'required|min:8',
                'repeatNewPassword' => 'required|min:8'
            ]
        );

        if ($validator->fails()) {
            return $this->jsonError($validator->errors()->all(), 400);
        }

        $oldPassword = $request->input('oldPassword');
        $newPassword = $request->input('newPassword');
        $repeatNewPassword = $request->input('repeatNewPassword');

        if ($newPassword != $repeatNewPassword) {
            return $this->jsonError(['Passwords mismatch']);
        }

        if (Hash::check($oldPassword, $account->password) === false) {
            return $this->jsonError(['Invalid old password']);
        }

        $account->password = $newPassword;

        $account->save();

        return $this->jsonSuccess($account);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $account = Account::find($request->route('accounts'));

        if (!$account instanceof Account) {
            return $this->jsonError('Account not found.', 404);
        }

        if ($account->id !== $this->getCurrentProfile()->id && $this->getCurrentProfile()->admin !== true) {
            return $this->jsonError('Not enough permissions.', 403);
        }

        $account->delete();
        return $this->jsonSuccess(['id' => $account->id]);
    }

    /**
     * Send email with link to reset forgotten password
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $email = $request->get('email');

        $account = Account::where('email', '=', $email)->first();

        if (!$account) {
            return $this->jsonError('Account not found.', 404);
        }

        // Generate random token and timestamp and set to profile
        $passwordResetToken = md5(uniqid(rand(), true));
        $account->password_reset_token = $passwordResetToken;
        $account->password_reset_time = (int) Carbon::now()->format('U');
        $account->save();

        // Send email with link for password reset
        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
        $webDomain .= 'reset-password';
        $data = [
            'token' => $passwordResetToken,
            'webDomain' => $webDomain
        ];

        $view = 'emails.password.password-reset';
        $subject = 'Password reset confirmation link!';

        if (! MailSend::send($view, $data, $account, $subject)) {
            return $this->jsonError('Issue with sending password reset email.');
        };

        return $this->jsonSuccess(
            [
                'messages' => ['You will shortly receive an email with the link to reset your password.']
            ]
        );
    }

    /**
     * Reset password
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $token = $request->get('token');

        if (!$token) {
            return $this->jsonError('Token not provided.', 404);
        }

        $account = Account::where('password_reset_token', '=', $token)->first();

        if (!$account) {
            return $this->jsonError('Invalid token provided.', 400);
        }

        // Check timestamps
        $unixNow = (int) Carbon::now()->format('U');
        if ($unixNow - $account->password_reset_time > 86400) {
            return $this->jsonError('Token has expired.', 400);
        }

        // Validate password
        $validator = Validator::make(
            $request->all(),
            [
                'newPassword' => 'required|min:8',
                'repeatNewPassword' => 'required|min:8'
            ]
        );

        if ($validator->fails()) {
            return $this->jsonError($validator->errors()->all(), 400);
        }

        $newPassword = $request->get('newPassword');
        $repeatNewPassword = $request->get('repeatNewPassword');

        if ($newPassword !== $repeatNewPassword) {
            return $this->jsonError(['Passwords mismatch']);
        }

        // Reset token and set new profile password
        $account->password_reset_token = null;
        $account->setPasswordAttribute($newPassword);

        if ($account->save()) {
            $view = 'emails.password.password-changed';
            $subject = 'Password successfully changed!';
            MailSend::send($view, [], $account, $subject);

            return $this->jsonSuccess([
                'messages' => ['Password successfully changed.']
            ]);
        };

        return $this->jsonError(
            [
                'errors' => ['Issue with saving new password']
            ]
        );
    }
}
