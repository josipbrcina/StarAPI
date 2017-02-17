<?php

namespace App\Listeners;

use App\Events\ModelUpdate;
use App\GenericModel;
use App\Helpers\InputHandler;
use App\Helpers\Slack;
use App\Profile;
use App\Services\ProfilePerformance;
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

        $profilePerformance = new ProfilePerformance();

        GenericModel::setCollection('tasks');

        $taskPerformance = $profilePerformance->perTask($task);

        foreach ($taskPerformance as $profileId => $taskDetails) {
            if ($taskDetails['taskCompleted'] === false) {
                return false;
            }

            if ($taskDetails['taskLastOwner'] === false) {
                continue;
            }

            $taskOwnerProfile = Profile::find($profileId);

            GenericModel::setCollection('tasks');
            $mappedValues = $profilePerformance->getTaskValuesForProfile($taskOwnerProfile, $task);
            GenericModel::setCollection('projects');

            $estimatedSeconds = max(InputHandler::getFloat($mappedValues['estimatedHours']) * 60 * 60, 1);

            $secondsWorking = $taskDetails['workSeconds'];

            $taskSpeedCoefficient = $secondsWorking / $estimatedSeconds;

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

            if ($secondsWorking > 0 && $estimatedSeconds > 1) {
                $xpDiff = 0;
                $message = null;
                $taskXp = (float) $taskOwnerProfile->xp <= 200 ? (float) $mappedValues['xp'] : 1.0;
                if ($taskSpeedCoefficient < 0.75) {
                    $xpDiff = $taskXp * $profilePerformance->getDurationCoefficient($task, $taskOwnerProfile)
                    * $this->taskPriorityCoefficient($taskOwnerProfile, $task);
                    $message = 'Early task delivery: ' . $taskLink;
                } elseif ($taskSpeedCoefficient > 1 && $taskSpeedCoefficient <= 1.1) {
                    $xpDiff = -1;
                    $message = 'Late task delivery: ' . $taskLink;
                } elseif ($taskSpeedCoefficient > 1.1 && $taskSpeedCoefficient <= 1.25) {
                    $xpDiff = -2;
                    $message = 'Late task delivery: ' . $taskLink;
                } elseif ($taskSpeedCoefficient > 1.25) {
                    $xpDiff = -3;
                    $message = 'Late task delivery: ' . $taskLink;
                } else {
                    // TODO: handle properly
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

    /**
     * Calculate task priority coefficient
     * @param Profile $taskOwner
     * @param GenericModel $task
     * @return float|int
     */
    private function taskPriorityCoefficient(Profile $taskOwner, GenericModel $task)
    {
        $taskPriorityCoefficient = 1;

        //get all projects that user is a member of
        $preSetcollection = GenericModel::getCollection();
        GenericModel::setCollection('projects');
        $taskOwnerprojects = GenericModel::whereIn('members', [$taskOwner->id])
            ->get();

        GenericModel::setCollection('tasks');

        $unassignedTasksPriority = [];

        //get all unassigned tasks from projects that user is a member of, and make list of tasks priority
        foreach ($taskOwnerprojects as $project) {
            $projectTasks = GenericModel::where('project_id', '=', $project->id)
                ->get();
            foreach ($projectTasks as $projectTask) {
                if (empty($projectTask->owner) && !in_array($projectTask->priority, $unassignedTasksPriority)) {
                    $unassignedTasksPriority[$projectTask->id] = $projectTask->priority;
                }
            }
        }

        //check task priority and compare with list of unassigned tasks priority and set task priority coefficient
        if ($task->priority === 'Low'
            && (in_array('Medium', $unassignedTasksPriority) || in_array('High', $unassignedTasksPriority))) {
            $taskPriorityCoefficient = 0.5;
        }

        if ($task->priority === 'Medium' && in_array('High', $unassignedTasksPriority)) {
            $taskPriorityCoefficient = 0.8;
        }

        GenericModel::setCollection($preSetcollection);

        return $taskPriorityCoefficient;
    }
}
