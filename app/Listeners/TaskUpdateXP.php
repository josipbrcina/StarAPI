<?php

namespace App\Listeners;

use App\Events\ModelUpdate;
use App\GenericModel;
use App\Helpers\InputHandler;
use App\Helpers\Slack;
use App\Profile;
use App\Services\ProfilePerformance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Class TaskUpdateXP
 * @package App\Listeners
 */
class TaskUpdateXP
{
    /**
     * @param ModelUpdate $event
     * @return bool
     */
    public function handle(ModelUpdate $event)
    {
        $task = $event->model;

        // Make sure not to update user XP if task is edited after QA has passed
        if ($task->isDirty() !== true) {
            return false;
        } else {
            $updatedFields = $task->getDirty();
            if (!key_exists('passed_qa', $updatedFields) || $updatedFields['passed_qa'] !== true) {
                return false;
            }
        }

        $profilePerformance = new ProfilePerformance();

        GenericModel::setCollection('tasks');

        $taskPerformance = $profilePerformance->perTask($task);

        foreach ($taskPerformance as $profileId => $taskDetails) {
            if ($taskDetails['taskLastOwner'] === false) {
                continue;
            }

            $taskOwnerProfile = Profile::find($profileId);

            GenericModel::setCollection('tasks');
            $mappedValues = $profilePerformance->getTaskValuesForProfile($taskOwnerProfile, $task);

            $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
            $taskLink = '['
                . $task->title
                . ']('
                . $webDomain
                . 'projects/'
                . $task->project_id
                . '/sprints/'
                . $task->sprint_id
                . '/tasks/'
                . $task->_id
                . ')';

            $taskFinishedUnixTime = InputHandler::getUnixTimestamp($taskDetails['workTrackTimestamp']);
            $taskDueDateUnixTime = InputHandler::getUnixTimestamp($task->due_date);
            $taskFinishedDate = Carbon::createFromFormat('U', $taskFinishedUnixTime)->format('Y-m-d');
            $taskDueDate = Carbon::createFromFormat('U', $taskDueDateUnixTime)->format('Y-m-d');
            $taskXp = (float) $mappedValues['xp'];
            $taskXpDeduction = (float) $mappedValues['xpDeduction'];

            if ($taskFinishedDate <= $taskDueDate) {
                $xpDiff = $taskXp;
                $message = 'Task Delivered on time: ' . $taskLink;
            } else {
                $xpDiff = $taskXpDeduction < 1 ? -1 : -($taskXpDeduction); // Deduct at least 1 xp
                $message = 'Late task delivery: ' . $taskLink;
            }

            if ($xpDiff !== 0) {
                $profileXpRecord = $this->getXpRecord($taskOwnerProfile);

                $records = $profileXpRecord->records;
                $records[] = [
                    'xp' => $xpDiff,
                    'details' => $message,
                    'timestamp' => (int) ((new \DateTime())->format('U') . '000') // Microtime
                ];
                $profileXpRecord->records = $records;
                $profileXpRecord->save();

                $taskOwnerProfile->xp += $xpDiff;
                $taskOwnerProfile->save();

                $this->sendSlackMessageXpUpdated($taskOwnerProfile, $task, $xpDiff);
            }

            if ($taskDetails['qaProgressSeconds'] > 30 * 60) {
                $poXpDiff = -3;
                $poMessage = 'Failed to review PR in time for ' . $taskLink;
            } else {
                $poXpDiff = 0.25;
                $poMessage = 'Review PR in time for ' . $taskLink;
            }

            // Get project owner id
            GenericModel::setCollection('projects');
            $project = GenericModel::find($task->project_id);
            $projectOwner = null;
            if ($project) {
                $projectOwner = Profile::find($project->acceptedBy);
            }

            if ($projectOwner) {
                $projectOwnerXpRecord = $this->getXpRecord($projectOwner);
                $records = $projectOwnerXpRecord->records;
                $records[] = [
                    'xp' => $poXpDiff,
                    'details' => $poMessage,
                    'timestamp' => (int) ((new \DateTime())->format('U') . '000') // Microtime
                ];
                $projectOwnerXpRecord->records = $records;
                $projectOwnerXpRecord->save();

                $projectOwner->xp += $poXpDiff;
                $projectOwner->save();

                $this->sendSlackMessageXpUpdated($projectOwner, $task, $poXpDiff);
            }
        }

        return true;
    }

    /**
     * @param Profile $profile
     * @return GenericModel
     */
    private function getXpRecord(Profile $profile)
    {
        $oldCollection = GenericModel::getCollection();
        GenericModel::setCollection('xp');
        if (!$profile->xp_id) {
            $profileXp = new GenericModel(['records' => []]);
            $profileXp->save();
            $profile->xp_id = $profileXp->_id;
        } else {
            $profileXp = GenericModel::find($profile->xp_id);
        }
        GenericModel::setCollection($oldCollection);

        return $profileXp;
    }

    /**
     * Send slack message about XP change
     * @param $profile
     * @param $task
     * @param $xpDiff
     */
    private function sendSlackMessageXpUpdated($profile, $task, $xpDiff)
    {
        $xpUpdateMessage = Config::get('sharedSettings.internalConfiguration.profile_update_xp_message');
        $webDomain = Config::get('sharedSettings.internalConfiguration.webDomain');
        $recipient = '@' . $profile->slack;
        $slackMessage = str_replace('{N}', ($xpDiff > 0 ? "+" . $xpDiff : $xpDiff), $xpUpdateMessage)
            . ' *'
            . $task->title
            . '* ('
            . $webDomain
            . 'projects/'
            . $task->project_id
            . '/sprints/'
            . $task->sprint_id
            . '/tasks/'
            . $task->_id
            . ')';
        Slack::sendMessage($recipient, $slackMessage, Slack::HIGH_PRIORITY);
    }
}
