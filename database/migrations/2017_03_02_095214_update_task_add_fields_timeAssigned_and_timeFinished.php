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
                                $task->timeAssigned = (int) $workStats['timeAssigned'];
                                $task->timeFinished = (int) $workStats['workTrackTimestamp'];
                                $task->save();
                            }
                        }
                    } else {
                        foreach ($task->task_history as $historyItem) {
                            if ($historyItem['status'] === 'claimed' || $historyItem['status'] === 'assigned') {
                                $task->timeAssigned = (int) $historyItem['timestamp'];
                            }
                            if ($historyItem['status'] === 'qa_success') {
                                $task->timeFinished = (int) $historyItem['timestamp'];
                                $task->save();
                            }
                        }
                    }
                } else {
                    if (isset($task->work)) {
                        foreach ($task->work as $workStatistic) {
                            if (!key_exists('timeRemoved', $workStatistic)) {
                                $task->timeAssigned = (int) $workStatistic['timeAssigned'];
                                $task->save();
                            }
                        }
                    } else {
                        foreach ($task->task_history as $historyItem) {
                            if ($historyItem['status'] === 'claimed' || $historyItem['status'] === 'assigned') {
                                $task->timeAssigned = (int) $historyItem['timestamp'];
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
