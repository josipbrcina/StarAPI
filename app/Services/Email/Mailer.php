<?php

namespace App\Services\Email;

/**
 * Class Mailer
 * @package App\Services\Email
 */
abstract class Mailer implements EmailProviderInterface
{
    private $to;
    private $from;
    private $subject;
    private $textBody = '';
    private $htmlBody = '';
    private $options = [
        'cc' => '',
        'bcc' => '',
    ];

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

    public function setTextBody($textBody)
    {
        $this->textBody = $textBody;
    }

    public function setHtmlBody($htmlBody)
    {
        $this->htmlBody = $htmlBody;
    }

    public function setOptions($options)
    {
        $this->options = $options;
    }

    public function getTo()
    {
        return $this->to;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getTextBody()
    {
        return $this->textBody;
    }

    public function getHtmlBody()
    {
        return $this->htmlBody;
    }

    public function getOptions()
    {
        return $this->options;
    }
}
