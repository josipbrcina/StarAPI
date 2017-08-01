<?php

namespace App\Services\Email;

use DotBlue\Mandrill\Exporters\MessageExporter;
use DotBlue\Mandrill\Mandrill as MandrillSender;
use DotBlue\Mandrill\Message;

/**
 * Class Mandrill
 * @package App\Services\Email
 */
class Mandrill extends Mailer
{
    public function send()
    {
        $apiKey = env('MANDRILL_API_KEY');
        $mandrill = new MandrillSender($apiKey);
        $mailer = new \DotBlue\Mandrill\Mailer(new MessageExporter(), $mandrill);

        $options = $this->getOptions();

        $message = new Message();
        $message->setFrom($this->getFrom());
        $message->setSubject($this->getSubject());
        $message->addTo($this->getTo());
        $message->setHtml($this->getHtmlBody());
        $message->setText($this->getTextBody());
        if (array_key_exists('cc', $options)) {
            $message->addCc($options['cc']);
        }
        if (array_key_exists('bcc', $options)) {
            $message->addBcc($options['bcc']);
        }

        $mailer->send($message);
    }
}
