<?php

namespace App\Console\Commands;

use App\GenericModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Vluzrmos\SlackApi\Facades\SlackChat;

/**
 * Class SlackSendMessages
 * @package App\Console\Commands
 */
class SlackSendMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:send-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send slack messages with delay';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Load configuration
        $priorityMapping = Config::get('sharedSettings.internalConfiguration.slack.priorityToMinutesDelay');

        $sent = [];

        $now = new \DateTime();
        $unixNow = (int) $now->format('U');
        $currentMinuteUnix = $unixNow - $unixNow % 60; // First second of current minute
        $nextMinuteUnix = $currentMinuteUnix + 60; // First second of next minute

        // Load by priority
        foreach ($priorityMapping as $priority => $minutesDelay) {
            $query = GenericModel::whereTo('slackMessages')
            ->query();
            // Find messages in required priority
            $query->where('priority', '=', $priority)
                // Make sure we don't re-send things
                ->where('sent', '=', false)
                // Check when it was added and make sure that required delay is within current minute
                ->where('runAt', '<', $nextMinuteUnix);

            $messages = $query->get();

            foreach ($messages as $message) {
                SlackChat::message($message->recipient, $message->message);
                $message->sent = true;
                $message->save();
                $sent[] = $message->recipient;
            }
        }

        return $sent;
    }
}
