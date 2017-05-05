<?php

namespace {

    use App\GenericModel;
    use Illuminate\Database\Migrations\Migration;

    class FixWorkHistory extends Migration
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
                $work = $task->work;
                if (!$work) {
                    $work = [];
                }

                $newWork = [];

                foreach ($work as $userId => $history) {
                    if (!isset($history['qa_in_progress'])) {
                        $history['qa_in_progress'] = 0;
                    }

                    if (!isset($history['qa_total_time'])) {
                        $history['qa_total_time'] = 0;
                    }

                    $newWork[$userId] = $history;
                }

                if ($newWork !== $work) {
                    $task->work = $newWork;
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
