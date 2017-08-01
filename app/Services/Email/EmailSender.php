<?php

namespace App\Services;

use App\Services\Email\EmailProviderInterface;

/**
 * Class EmailSender
 * @package App\Services
 */
class EmailSender
{
    /**
     * @var EmailProviderInterface
     */
    private $emailProviderInterface;

    /**
     * EmailSender constructor.
     * @param EmailProviderInterface $emailProviderInterface
     */
    public function __construct(EmailProviderInterface $emailProviderInterface)
    {
        $this->emailProviderInterface = $emailProviderInterface;
    }

    public function setTo($to)
    {
        $this->emailProviderInterface->setTo($to);
    }

    public function setFrom($from)
    {
        $this->emailProviderInterface->setFrom($from);
    }

    public function setSubject($subject)
    {
        $this->emailProviderInterface->setSubject($subject);
    }

    public function setTextBody($textBody)
    {
        $this->emailProviderInterface->setTextBody($textBody);
    }

    public function setHtmlBody($htmlBody)
    {
        $this->emailProviderInterface->setHtmlBody($htmlBody);
    }

    public function setOptions($options)
    {
        $this->emailProviderInterface->setOptions($options);
    }

    public function send()
    {
        $this->emailProviderInterface->send();
    }
}
