<?php

namespace App\Services\Email;

use App\Services\Email\EmailProviderInterface as EmailProviderInterface;
use Mailgun\Mailgun as MailGunSender;

/**
 * Class MailGun
 * @package App\Services\Email
 */
class MailGun implements EmailProviderInterface
{
    private $to;
    private $from;
    private $subject;
    private $body;

    public function setTo($to)
    {
        $this->to = $to;
    }

    public function setFrom($from)
    {
        $this->from = $from;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function send()
    {
        $apiKey = env(MAILGUN_KEY);
        $domain = env(MAILGUN_DOMAIN);

        $mg = MailGunSender::create($apiKey);
        $mg->messages()->send(
            $domain,
            [
                'from' => $this->from,
                'to' => $this->to,
                'subject' => $this->subject,
                'text' => $this->body
            ]
        );
    }
}
