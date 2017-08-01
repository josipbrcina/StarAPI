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
     * Set email text body
     * @param $textBody
     * @return mixed
     */
    public function setTextBody($textBody);

    /**
     * Set email html body
     * @param $htmlBody
     * @return mixed
     */
    public function setHtmlBody($htmlBody);

    /**
     * Send email
     * @return mixed
     */

    /**
     * Set additional headers
     * @param $options
     * @return mixed
     */
    public function setOptions($options);

    /**
     * Send email
     * @return mixed
     */
    public function send();
}
