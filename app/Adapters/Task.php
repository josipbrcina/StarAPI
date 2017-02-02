<?php

namespace App\Adapters;

use App\GenericModel;
use App\Profile;
use App\Services\ProfilePerformance;
use Illuminate\Support\Facades\Auth;

class Task implements AdaptersInterface
{
    public $task;

    public function __construct(GenericModel $model)
    {
        $this->task = $model;
    }

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

        $this->task->estimate = sprintf('%.2f', $this->task->estimatedHours);
        $this->task->estimatedHours = $originalEstimate;
        $this->task->xp = sprintf('%.2f', $this->task->xp);
        $this->task->payout = sprintf('%.2f', $mappedValues['payout']);

        $taskStatus = $profilePerformance->perTask($this->task);

        $colorIndicator = '';
        
        if (!empty($taskStatus)) {
            $taskEstimatedSeconds = $mappedValues['estimatedHours'] * 60 * 60;
            $taskDeliveredOnTime = $taskStatus[$this->task->owner]['workSeconds'] <= $taskEstimatedSeconds;

            // deadline in last 25% of the time of task
            $lastQuarterOfTask = 0.25 * $taskEstimatedSeconds;

            //generate task color status
            if (($taskEstimatedSeconds - $taskStatus[$this->task->owner]['workSeconds']) <= $lastQuarterOfTask) {
                $colorIndicator = 'orange';
            }

            if ($taskDeliveredOnTime === false) {
                $colorIndicator = 'red';
            }

            if ($this->task->paused === true) {
                $colorIndicator = 'yellow';
            }

            if ($this->task->submitted_for_qa === true) {
                $colorIndicator = 'blue';
            }

            if ($this->task->passed_qa === true) {
                $colorIndicator = 'green';
            }
        }

        $this->task->colorIndicator = $colorIndicator;

        return $this->task;
    }
}
