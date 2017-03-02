<?php

namespace {

    use App\GenericModel;
    use Illuminate\Database\Migrations\Migration;

    class UpdateTaskAddFieldsTimeAssignedAndTimeFinished extends Migration
    {
        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            GenericModel::setCollection('tasks');
            $tasks = GenericModel::all();
            foreach ($tasks as $task) {
                if (empty($task->owner)) {
                    continue;
                }
                if ($task->passed_qa === true) {
                    if (isset($task->work)) {
                        foreach ($task->work as $workStats) {
                            if (!key_exists('timeRemoved', $workStats)) {
                                $task->timeAssigned = $workStats['timeAssigned'];
                                $task->timeFinished = $workStats['workTrackTimestamp'];
                                $task->save();
                            }
                        }
                    } else {
                        foreach ($task->task_history as $historyItem) {
                            if ($historyItem['status'] === 'claimed' || $historyItem['status'] === 'assigned') {
                                $task->timeAssigned = $historyItem['timestamp'];
                            }
                            if ($historyItem['status'] === 'qa_success') {
                                $task->timeFinished = $historyItem['timestamp'];
                                $task->save();
                            }
                        }
                    }
                } else {
                    if (isset($task->work)) {
                        foreach ($task->work as $workStatistic) {
                            if (!key_exists('timeRemoved', $workStatistic)) {
                                $task->timeAssigned = $workStatistic['timeAssigned'];
                                $task->timeFinished = null;
                                $task->save();
                            }
                        }
                    } else {
                        foreach ($task->task_history as $historyItem) {
                            if ($historyItem['status'] === 'claimed' || $historyItem['status'] === 'assigned') {
                                $task->timeAssigned = $historyItem['timestamp'];
                                $task->timeFinished = null;
                                $task->save();
                            }
                        }
                    }
                }
            }
        }

        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            //
        }
    }
}
