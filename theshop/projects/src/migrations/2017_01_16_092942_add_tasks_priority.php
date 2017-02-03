<?php

namespace {

    use Illuminate\Database\Migrations\Migration;
    use App\GenericModel;

    class AddTasksPriority extends Migration
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
                if (empty($task->priority)) {
                    $task->update([
                        'priority' => 'Medium'
                    ]);
                }
            }
        }
        
        public function down()
        {
        }
    }
}
