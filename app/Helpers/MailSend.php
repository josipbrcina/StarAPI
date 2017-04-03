<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;

/**
 * Class MailSend
 * @package App\Helpers
 */
class MailSend
{
    /**
     * Send email to user
     * @param $view
     * @param $data
     * @param $profile
     * @param $subject
     * @param array $attachments
     * @return bool
     */
    public static function send($view, $data, $profile, $subject, array $attachments = [])
    {
        $mailConfig = Config::get('mail.emails_enabled');

        if ($mailConfig === true) {
            $emailFrom = Config::get('mail.private_mail_from');
            $emailName = Config::get('mail.private_mail_name');

            \Mail::send($view, $data, function ($message) use (
                $profile,
                $emailFrom,
                $emailName,
                $subject,
                $attachments
            ) {
                $message->from($emailFrom, $emailName);
                $message->to($profile->email, $profile->name)->subject($emailName . ' - ' . $subject);
                foreach ($attachments as $FileNameAndExtension => $attachmentContent) {
                    $message->attachData($attachmentContent, $FileNameAndExtension);
                }
            });

            return true;
        }

        return false;
    }
}
