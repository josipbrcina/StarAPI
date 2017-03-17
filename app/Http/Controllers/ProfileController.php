<?php

namespace App\Http\Controllers;

use App\Events\ProfileUpdate;
use App\GenericModel;
use App\Helpers\InputHandler;
use App\Services\ProfilePerformance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Profile;
use Illuminate\Http\Request;
use App\Helpers\Configuration;
use App\Helpers\MailSend;

/**
 * Class ProfileController
 * @package App\Http\Controllers
 */
class ProfileController extends Controller
{
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

        $profile = Auth::user();

        return $this->jsonSuccess($profile);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $profiles = Profile::all();
        return $this->jsonSuccess($profiles);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPerformance(Request $request)
    {
        // Default last month
        $lastMonthUnixStart = strtotime('first day of last month');
        $lastMonthUnixEnd = strtotime('last day of last month');

        $startDate = InputHandler::getUnixTimestamp($request->input('unixStart', $lastMonthUnixStart));
        $endDate = InputHandler::getUnixTimestamp($request->input('unixEnd', $lastMonthUnixEnd));

        $profile = Profile::find($request->route('id'));
        if (!$profile) {
            return $this->jsonError(
                ['Profile not found'],
                404
            );
        }

        $performance = new ProfilePerformance();

        return $this->jsonSuccess($performance->aggregateForTimeRange($profile, $startDate, $endDate));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPerformancePerTask(Request $request)
    {
        GenericModel::setCollection('tasks');
        $task = GenericModel::find($request->route('taskId'));

        if (!$task) {
            return $this->jsonError(
                ['Task not found'],
                404
            );
        }

        $performance = new ProfilePerformance();

        return $this->jsonSuccess($performance->perTask($task));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeedback(Request $request)
    {
        GenericModel::setCollection('feedbacks');

        $feedback = GenericModel::where('userId', '=', $request->route('id'))
            ->get();

        // Check if collection has got any models returned
        if (count($feedback) > 0) {
            return $this->jsonSuccess($feedback);
        }

        return $this->jsonError(
            ['No feedback found.'],
            404
        );
    }

    /**
     * @param Request $request
     * @return GenericModel|\Illuminate\Http\JsonResponse
     */
    public function storeFeedback(Request $request)
    {
        $profile = Profile::find($request->route('id'));

        // Check if user id exists
        if (!$profile) {
            return $this->jsonError(
                ['Profile not found'],
                404
            );
        }

        $requestFields = $request->all();
        if (empty($requestFields)) {
            $requestFields = [];
        }

        // Validate request fields
        $allowedFields = [
            'createdAt',
            'answers'
        ];

        $validateFields = array_diff_key($requestFields, array_flip($allowedFields));

        if (!empty($validateFields)
            || !key_exists('createdAt', $requestFields)
            || !key_exists('answers', $requestFields)
        ) {
            return $this->jsonError('Invalid input. Request must have two fields - createdAt and answers');
        }

        $errors = [];

        if (is_array($requestFields['createdAt'])) {
            $errors[] = 'Invalid input format. createdAt field must not be type of array.';
        }

        if (!is_array($requestFields['answers'])) {
            $errors[] = 'Invalid input format. answers field must be type of array';
        }

        if (count($errors) > 0) {
            return $this->jsonError($errors);
        }

        // Validate createdAt field timestamp format
        try {
            InputHandler::getUnixTimestamp($requestFields['createdAt']);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage() . ' on createdAt field.');
        }

        // After all validations let's save model to feedbacks collection
        GenericModel::setCollection('feedbacks');

        $feedback = new GenericModel(
            [
                'userId' => $request->route('id'),
                'createdAt' => $requestFields['createdAt'],
                'answers' => $requestFields['answers']
            ]
        );

        if ($feedback->save()) {
            return $this->jsonSuccess($feedback);
        }

        return $this->jsonError('Issue with saving feedback.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $fields = $request->all();
        $this->validateInputsForResource($fields, 'profiles');

        $profile = Profile::create($fields);

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
            'name' => $profile->name,
            'email' => $profile->email,
            'github' => $profile->github,
            'trello' => $profile->trello,
            'slack' => $profile->slack,
            'teamSlack' => $teamSlackInfo
        ];
        $view = 'emails.registration';
        $subject = 'Welcome to The Shop platform!';

        MailSend::send($view, $data, $profile, $subject);

        return $this->jsonSuccess($profile);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $profile = Profile::find($request->route('profiles'));
        if (!$profile) {
            return $this->jsonError('User not found.', 404);
        }
        return $this->jsonSuccess($profile);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $profile = Profile::find($request->route('profiles'));

        if (!$profile instanceof Profile) {
            return $this->jsonError('Model not found.', 404);
        }

        if ($profile->id !== $this->getCurrentProfile()->id && $this->getCurrentProfile()->admin !== true) {
            return $this->jsonError('Not enough permissions.', 403);
        }

        $fields = $request->all();
        $this->validateInputsForResource($fields, 'profiles', null, ['email' => 'required|email']);

        $profile->fill($fields);

        if ($profile->isDirty()) {
            event(new ProfileUpdate($profile));
        }

        $profile->save();

        return $this->jsonSuccess($profile);
    }

    /**
     * Change password implementation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        $profile = $this->getCurrentProfile();

        if (!$profile instanceof Profile) {
            return $this->jsonError('User not found.', 404);
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

        if (Hash::check($oldPassword, $profile->password) === false) {
            return $this->jsonError(['Invalid old password']);
        }

        $profile->password = $newPassword;

        $profile->save();

        return $this->jsonSuccess($profile);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $profile = Profile::find($request->route('profiles'));

        if (!$profile instanceof Profile) {
            return $this->jsonError('User not found.', 404);
        }

        if ($profile->id !== $this->getCurrentProfile()->id && $this->getCurrentProfile()->admin !== true) {
            return $this->jsonError('Not enough permissions.', 403);
        }

        $profile->delete();
        return $this->jsonSuccess(['id' => $profile->id]);
    }
}
