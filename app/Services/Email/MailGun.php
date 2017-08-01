<?php

namespace App\Services\Email;

use Mailgun\Mailgun as MailGunSender;

/**
 * Class MailGun
 * @package App\Services\Email
 */
class MailGun extends Mailer
{
    public function send()
    {
        $apiKey = env('MAILGUN_API_KEY');
        $domain = env('MAILGUN_DOMAIN');

        $options = $this->getOptions();
        $parameters = [
            'from' => $this->getFrom(),
            'to' => $this->getTo(),
            'subject' => $this->getSubject(),
            'text' => $this->getTextBody(),
            'html' => $this->getHtmlBody(),
        ];

        if (array_key_exists('cc', $options)) {
            $parameters['cc'] = $options['cc'];
        }
        if (array_key_exists('bcc', $options)) {
            $parameters['bcc'] = $options['bcc'];
        }

        $mg = MailGunSender::create($apiKey);
        $mg->messages()->send($domain, $parameters);
    }
}
