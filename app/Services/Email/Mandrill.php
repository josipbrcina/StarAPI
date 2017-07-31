<?php

namespace App\Services\Email;

use App\Services\Email\EmailProviderInterface as EmailProviderInterface;

/**
 * Class Mandrill
 * @package App\Services\Email
 */
class Mandrill implements EmailProviderInterface
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
        //TODO: implement mandrill package and put logic here
    }
}
