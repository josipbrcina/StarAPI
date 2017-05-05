<?php

namespace {

    use App\GenericModel;
    use Illuminate\Database\Migrations\Migration;

    /**
     * Class UpdateTaskWorkFieldNumberQaFailed
     */
    class UpdateTaskWorkFieldNumberQaFailed extends Migration
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
                $work = [];
                if (isset($task->work)) {
                    $work = $task->work;
                    foreach ($work as $userId => $workstats) {
                        $work[$userId]['numberFailedQa'] = 0;
                        foreach ($task->task_history as $historyItem) {
                            if ($historyItem['user'] === $userId
                                && $historyItem['status'] === 'paused'
                                && $historyItem['event'] === 'Task paused because of: "Task failed QA"'
                            ) {
                                $work[$userId]['numberFailedQa']++;
                            }
                        }
                    }
                    $task->work = $work;
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
