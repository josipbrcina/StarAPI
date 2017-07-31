<?php

namespace App\Services\Email;

interface EmailProviderInterface
{
    /**
     * Set recipient
     * @param $to
     * @return mixed
     */
    public function setTo($to);

    /**
     * Set sender
     * @param $from
     * @return mixed
     */
    public function setFrom($from);

    /**
     * Set subject
     * @param $subject
     * @return mixed
     */
    public function setSubject($subject);

    /**
     * Set email body
     * @param $body
     * @return mixed
     */
    public function setBody($body);

    /**
     * Send email
     * @return mixed
     */
    public function send();
}
