<?php

namespace App\Console\Commands;

use App\GenericModel;
use App\Helpers\InputHandler;
use App\Helpers\Slack;
use App\Profile;
use Illuminate\Console\Command;

class NotifyAdminsTaskDeadline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ping:admins:task:deadline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ping admins / PO on slack about approaching task deadline 7 days before deadline';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        GenericModel::setCollection('tasks');
        $tasks = GenericModel::all();

        $unixDateNow = (new \DateTime())->format('U');
        $unixSevenDaysFromNow = (int) ($unixDateNow) + 24 * 7 * 60 * 60;
        $sevenDaysFromNowDate = \DateTime::createFromFormat('U', $unixSevenDaysFromNow)->format('Y-m-d');
        foreach ($tasks as $task) {
            if (!empty($task->due_date) && $sevenDaysFromNowDate
                === \DateTime::createFromFormat('U', InputHandler::getUnixTimestamp($task->due_date))->format('Y-m-d')
            ) {
                GenericModel::setCollection('projects');
                $taskProject = GenericModel::where('_id', '=', $task->project_id)->first();
                $adminsAndPo = Profile::where('admin', '=', true)
                    ->orWhere('_id', '=', $taskProject->acceptedBy)
                    ->get();

                foreach ($adminsAndPo as $user) {
                    if ($user->slack) {
                        $taskDueDate = \DateTime::createFromFormat('U', InputHandler::getUnixTimestamp($task->due_date))
                            ->format('Y-m-d');
                        $recipient = '@' . $user->slack;
                        $message = 'Hey, task *'
                            . $task->title
                            . '* on project *'
                            . $taskProject->name
                            . '* deadline is in *7 days* '
                            . '(*'
                            . $taskDueDate
                            . '*)';
                        Slack::sendMessage($recipient, $message, Slack::LOW_PRIORITY);
                    }
                }
            }
        }
    }
}
