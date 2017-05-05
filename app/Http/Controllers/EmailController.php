<?php

namespace App\Http\Controllers;

use App\GenericModel;
use Illuminate\Http\Request;
use App\Helpers\MailSend;

/**
 * Class EmailController
 * @package App\Http\Controllers
 */
class EmailController extends Controller
{
    /**
     * Send emails to list of user Ids
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEmail(Request $request)
    {
        $to = $request->input('to');
        $message = $request->input('message');
        $subject = $request->input('subject');

        $errors = [];

        $recipients = $this->loadProfiles($to, $message, $errors);

        if ($recipients === false) {
            return $this->jsonError($errors, 400);
        }

        //send email to list of users
        $data = [
            'email' => $message
        ];
        $view = 'emails.message';
        if (empty($subject)) {
            $subject = \Config::get('mail.private_mail_subject');
        }
        $sentTo = [];
        foreach ($recipients as $recipient) {
            MailSend::send($view, $data, $recipient, $subject);
            $sentTo[] = [
                'name' => $recipient->name,
                'email' => $recipient->email
            ];
        }

        return $this->jsonSuccess(
            [
                'sent' => true,
                'to' => $sentTo,
                'message' => $message
            ]
        );
    }

    /**
     * Validate input for sending emails
     * @param $to
     * @param $message
     * @param $errors
     * @return array|bool
     */
    private function loadProfiles($to, $message, &$errors)
    {
        if (empty($to)) {
            $errors[] = 'Empty recipient field.';
        }

        //check if message field is empty
        if (empty($message)) {
            $errors[] = 'Empty message field.';
        }

        $recipients = [];

        if (is_array($to)) {
            foreach ($to as $t) {
                $profile = GenericModel::whereTo('profiles')->find($t);
                if ($profile !== null) {
                    $recipients[] = $profile;
                }
            }
            if (empty($recipients)) {
                $errors[] = 'Invalid recipient input. Ids not found.';
            }
        } else {
            $errors[] = 'Invalid recipient input. Must be type of array.';
        }

        if (count($errors) > 0) {
            return false;
        }

        return $recipients;
    }
}
