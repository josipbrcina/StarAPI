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

    public function setBody($body)
    {
        $this->emailProviderInterface->setBody($body);
    }

    public function send()
    {
        $this->emailProviderInterface->send();
    }
}
