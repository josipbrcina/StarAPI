<?php

namespace App\Adapters;

use App\GenericModel;
use App\Helpers\AuthHelper;
use App\Helpers\InputHandler;
use App\Profile;
use App\Services\ProfilePerformance;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Class Task
 * @package App\Adapters
 */
class Task implements AdaptersInterface
{
    /**
     * @var GenericModel
     */
    public $task;

    /**
     * Task constructor.
     * @param GenericModel $model
     */
    public function __construct(GenericModel $model)
    {
        $this->task = $model;
    }

    /**
     * @return GenericModel
     */
    public function process()
    {
        $profilePerformance = new ProfilePerformance();

        $taskViewPermission = false;
        $account = AuthHelper::getAuthenticatedUser();
        $profile = Profile::find($account->_id);

        // Check if task has got owner
        if (!empty($this->task->owner)) {
            $profile = Profile::find($this->task->owner);
            $taskViewPermission = true;
        }

        // If task is not claimed (no owner) check if user is admin, Po or is task currently reserved by user
        if (empty($this->task->owner)) {
            $oldCollection = GenericModel::getCollection();
            GenericModel::setCollection('projects');
            $project = GenericModel::find($this->task->project_id);
            GenericModel::setCollection($oldCollection);

            if ($profile->admin || $profile->id === $project->acceptedBy) {
                $taskViewPermission = true;
            } elseif (isset($this->task->reservationsBy)) {
                $unixNow = Carbon::now()->format('U');
                $reservationTime =
                    Config::get('sharedSettings.internalConfiguration.tasks.reservation.maxReservationTime');
                foreach ($this->task->reservationsBy as $reservation) {
                    if ($unixNow - $reservation['timestamp'] <= ($reservationTime * 60)
                        && $reservation['user_id'] === $profile->id
                    ) {
                        $taskViewPermission = true;
                    }
                }
            }
        }

        // If user doesn't have permission to view all task properties modify them and return task
        if (!$taskViewPermission) {
            $taskProperties = $this->task->getAttributes();
            foreach ($taskProperties as $propertyName => $propertyValue) {
                if ($propertyName === 'ready'
                    || $propertyName === '_id'
                    || $propertyName === 'project_id'
                    || $propertyName === 'sprint_id'
                    || $propertyName === 'title'
                    || $propertyName === 'priority'
                    || $propertyName === 'due_date'
                    || $propertyName === 'price'
                    || $propertyName === 'skillset'
                ) {
                    $this->task->{$propertyName} = $propertyValue;
                } else {
                    $this->task->{$propertyName} = null;
                }
            }

            return $this->task;
        }

        // Set task properties for specific user
        $mappedValues = $profilePerformance->getTaskValuesForProfile($profile, $this->task);

        $originalEstimate = $this->task->estimatedHours;

        foreach ($mappedValues as $key => $value) {
            $this->task->{$key} = $value;
        }

        $this->task->estimate = (float)sprintf('%.2f', $this->task->estimatedHours);
        $this->task->estimatedHours = (float)$originalEstimate;
        $this->task->xp = (float)sprintf('%.2f', $this->task->xp);
        $this->task->payout = (float)sprintf('%.2f', $mappedValues['payout']);

        $taskStatus = $profilePerformance->perTask($this->task);

        // Set due dates so we can check them and generate colorIndicator
        $taskDueDate = Carbon::createFromFormat('U', InputHandler::getUnixTimestamp($this->task->due_date))
            ->format('Y-m-d');
        $dueDate2DaysFromNow = Carbon::now()->addDays(2)->format('Y-m-d');
        $dueDate7DaysFromNow = Carbon::now()->addDays(7)->format('Y-m-d');

        $colorIndicator = '';

        // Set colorIndicator to red if task due date in 2 days or less
        if ($taskDueDate <= $dueDate2DaysFromNow) {
            $colorIndicator = 'red';
        }

        // Generate task colorIndicator
        if (!empty($taskStatus)) {
            // If task is claimed and due_date is within next 3-7 days, set colorIndicator to orange
            if ($taskDueDate > $dueDate2DaysFromNow && $taskDueDate <= $dueDate7DaysFromNow) {
                $colorIndicator = 'orange';
            }
            // If task is paused set colorIndicator to yellow
            if ($this->task->paused === true) {
                $colorIndicator = 'yellow';
            }
            // If task is submitted for qa set colorIndicator to blue
            if ($this->task->submitted_for_qa === true) {
                $colorIndicator = 'blue';
            }
            // If task is blocked set colorIndicator to brown
            if ($this->task->blocked === true) {
                $colorIndicator = 'brown';
            }
            // If task is in qa in progress set colorIndicator to dark_green
            if ($this->task->qa_in_progress === true) {
                $colorIndicator = 'dark_green';
            }
        }

        // Set colorIndicator to green if task passed qa
        if ($this->task->passed_qa === true) {
            $colorIndicator = 'green';
        }

        $this->task->colorIndicator = $colorIndicator;

        return $this->task;
    }
}
