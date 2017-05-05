<?php

namespace {

    use App\GenericModel;
    use Illuminate\Database\Migrations\Migration;

    class UpdateTaskHistory extends Migration
    {
        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            $tasks = GenericModel::allModels('tasks');

            foreach ($tasks as $task) {
                if (empty($task->task_history)) {
                    $task->task_history = [];
                }

                $newHistory = [];
                foreach ($task->task_history as $historyItem) {
                    if ($historyItem['event'] === 'Task passed QA!') {
                        $historyItem['status'] = 'qa_success';
                        $historyItem['event'] = 'Task passed QA';
                    } elseif ($historyItem['event'] === 'Task submitted for QA') {
                        $historyItem['status'] = 'qa_ready';
                        $historyItem['event'] = 'Task ready for QA';
                    } elseif ($historyItem['event'] === 'Task returned to development') {
                        $historyItem['status'] = 'qa_fail';
                        $historyItem['event'] = 'Task failed QA';
                    } elseif ($historyItem['event'] === 'Task resumed') {
                        $historyItem['status'] = 'resumed';
                    } elseif (preg_match('/^Task paused because of: (.+)$/', $historyItem['event'], $result)) {
                        $historyItem['status'] = 'paused';
                    } elseif (preg_match('/^Task claimed by (.+)$/', $historyItem['event'], $result)) {
                        $historyItem['status'] = 'claimed';
                        $historyItem['event'] = 'Task claimed by ' . $result[1];
                    } elseif (preg_match('/^Task assigned to user (.+)$/', $historyItem['event'], $result)) {
                        $historyItem['status'] = 'assigned';
                        $historyItem['event'] = 'Task assigned by ' . $result[1];
                    }
                    $newHistory[] = $historyItem;
                }

                $task->task_history = $newHistory;

                $task->save();
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
