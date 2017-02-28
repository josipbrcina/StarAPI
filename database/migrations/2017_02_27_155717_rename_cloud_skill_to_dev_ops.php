<?php

namespace {

    use App\GenericModel;
    use App\Profile;
    use Illuminate\Database\Migrations\Migration;

    /**
     * Class RenameCloudSkillToDevOps
     */
    class RenameCloudSkillToDevOps extends Migration
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
            // Rename Cloud skill to DevOps on all tasks
            foreach ($tasks as $task) {
                if (isset($task->skillset) && is_array($task->skillset) && in_array('Cloud', $task->skillset)) {
                    $skillSet = $task->skillset;
                    $needle = "Cloud";
                    $replacement = "DevOps";
                    for ($i = 0; $i < count($skillSet); $i++) {
                        if ($skillSet[$i] === $needle) {
                            $skillSet[$i] = $replacement;
                        }
                    }
                    $task->skillset = $skillSet;
                    $task->save();
                }
            }

            $profiles = Profile::all();
            // Rename Cloud skill to DevOps on all profiles
            foreach ($profiles as $profile) {
                if (isset($profile->skills) && is_array($profile->skills) && in_array('Cloud', $profile->skills)) {
                    $skills = $profile->skills;
                    $skillNeedle = "Cloud";
                    $replacementSkill = "DevOps";
                    for ($i = 0; $i < count($skills); $i++) {
                        if ($skills[$i] === $skillNeedle) {
                            $skills[$i] = $replacementSkill;
                        }
                    }
                    $profile->skills = $skills;
                    $profile->save();
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
