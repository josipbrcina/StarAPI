<?php

namespace {

    use Illuminate\Database\Migrations\Migration;
    use App\GenericModel;

    class UpdateProfileRole extends Migration
    {
        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            $profiles = GenericModel::whereTo('profiles')->all();
            foreach ($profiles as $profile) {
                if (empty($profile->role) && $profile->admin === true) {
                    $profile->update([
                        'role' => 'admin'
                    ]);
                }

                if (empty($profile->role) && $profile->employee === true) {
                    $profile->update([
                        'role' => 'employee'
                    ]);
                }

                if (empty($profile->role)) {
                    $profile->update([
                        'role' => 'standard'
                    ]);
                }
            }
        }

        public function down()
        {
        }
    }
}
