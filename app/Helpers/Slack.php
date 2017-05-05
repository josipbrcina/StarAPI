<?php

namespace App\Helpers;

use App\GenericModel;
use Illuminate\Support\Facades\Config;

/**
 * Class Slack
 * @package App\Helpers
 */
class Slack
{
    const HIGH_PRIORITY = 0;
    const MEDIUM_PRIORITY = 1;
    const LOW_PRIORITY = 2;

    /**
     * @param $recipient
     * @param $message
     * @param int $priority
     * @return GenericModel
     */
    public static function sendMessage($recipient, $message, $priority = self::MEDIUM_PRIORITY)
    {
        // Load configuration
        $priorityMapping = Config::get('sharedSettings.internalConfiguration.slack.priorityToMinutesDelay');

        $unixNow = (int) (new \DateTime())->format('U');
        $secondsDelay = $priorityMapping[$priority] * 60;

        // Round to next interval (delay) based on priority
        $runAt = $unixNow - $unixNow % $secondsDelay + $secondsDelay;

        $record = new GenericModel();
        $record->recipient = $recipient;
        $record->message = $message;
        $record->priority = $priority;
        $record->sent = false;
        $record->runAt = $runAt;
        $record->saveModel('slackMessages');

        return $record;
    }
}
