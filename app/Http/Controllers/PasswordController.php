<?php

namespace App\Http\Controllers;

use App\Profile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Helpers\MailSend;
use Illuminate\Support\Facades\Validator;

/**
 * Class PasswordController
 * @package App\Http\Controllers\Auth
 */
class PasswordController extends Controller
{
    /**
     * Send email with link to reset forgotten password
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $email = $request->get('email');

        $profile = Profile::where('email', '=', $email)->first();

        if (!$profile) {
            return $this->jsonError('User not found.', 404);
        }

        // Generate random token and timestamp and set to profile
        $passwordResetToken = md5(uniqid(rand(), true));
        $profile->password_reset_token = $passwordResetToken;
        $profile->password_reset_time = (int) Carbon::now()->format('U');
        $profile->save();

        // Send email with link for password reset
        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
        $webDomain .= 'reset-password';
        $data = [
            'token' => $passwordResetToken,
            'webDomain' => $webDomain
        ];

        $view = 'emails.password.password-reset';
        $subject = 'Password reset confirmation link!';

        if (! MailSend::send($view, $data, $profile, $subject)) {
            return $this->jsonError('Issue with sending password reset email.');
        };

        return $this->jsonSuccess('You will shortly receive an email with the link to reset your password.');
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

        $profile = Profile::where('password_reset_token', '=', $token)->first();

        if (!$profile) {
            return $this->jsonError('Invalid token provided.', 400);
        }

        // Check timestamps
        $unixNow = (int) Carbon::now()->format('U');
        if ($unixNow - $profile->password_reset_time > 86400) {
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
        $profile->password_reset_token = null;
        $profile->setPasswordAttribute($newPassword);

        if ($profile->save()) {
            return $this->jsonSuccess('Password successfully changed.');
        };

        return $this->jsonError('Issue with saving new password');
    }
}
