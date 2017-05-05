<?php

namespace {

    use App\GenericModel;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Database\Migrations\Migration;

    class UpdateTaskHistoryAddOwner extends Migration
    {
        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            $tasks = GenericModel::whereTo('tasks')->all();

            foreach ($tasks as $task) {
                if (empty($task->owner)) {
                    continue;
                }

                if (empty($task->task_history)) {
                    $task->task_history = [];
                }

                $newHistory = [];
                $skip = false;
                $firstTimestamp = null;
                foreach ($task->task_history as $historyItem) {
                    if (!array_key_exists('status', $historyItem)) {
                        $skip = true;
                        continue;
                    }

                    if ($historyItem['status'] === 'assigned' || $historyItem['status'] === 'claimed') {
                        $skip = true;
                    }

                    if (!$firstTimestamp) {
                        $firstTimestamp = $historyItem['timestamp'];
                    }

                    $newHistory[] = $historyItem;
                }

                $assignedHistory = [
                    'user' => $task->owner,
                    'timestamp' => $firstTimestamp - 3600000, // hour early
                    'event' => 'Task assigned by migration script',
                    'status' => 'assigned',
                ];

                array_unshift($newHistory, $assignedHistory);

                $task->task_history = $newHistory;

                if (!$skip || !$firstTimestamp) {
                    $task->save();
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
