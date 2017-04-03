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
     * @param null $pdf
     * @return bool
     */
    public static function send($view, $data, $profile, $subject, $pdf = null)
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
                $pdf
            ) {
                $message->from($emailFrom, $emailName);
                $message->to($profile->email, $profile->name)->subject($emailName . ' - ' . $subject);
                if ($pdf !== null) {
                    $message->attachData($pdf, 'SalaryReport.pdf');
                }
            });

            return true;
        }

        return false;
    }
}
