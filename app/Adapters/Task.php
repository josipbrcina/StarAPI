<?php

namespace App\Adapters;

use App\GenericModel;
use App\Helpers\InputHandler;
use App\Profile;
use App\Services\ProfilePerformance;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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

        $profile = Auth::user();
        if (!empty($this->task->owner)) {
            $profile = Profile::find($this->task->owner);
        }

        $mappedValues = $profilePerformance->getTaskValuesForProfile($profile, $this->task);

        $originalEstimate = $this->task->estimatedHours;

        foreach ($mappedValues as $key => $value) {
            $this->task->{$key} = $value;
        }

        $this->task->estimate = (float) sprintf('%.2f', $this->task->estimatedHours);
        $this->task->estimatedHours = (float) $originalEstimate;
        $this->task->xp = (float) sprintf('%.2f', $this->task->xp * 2); // Multiply basic xp by 2
        $this->task->payout = (float) sprintf('%.2f', $mappedValues['payout']);

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
