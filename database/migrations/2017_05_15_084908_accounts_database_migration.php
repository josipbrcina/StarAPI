<?php

namespace {

    use App\GenericModel;
    use Illuminate\Database\Migrations\Migration;

    class AccountsDatabaseMigration extends Migration
    {
        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            $profiles = GenericModel::whereTo('profiles')->all();

            $applications = ['starapi'];

            foreach ($profiles as $profile) {
                $account = new GenericModel([
                    'name' => $profile->name,
                    'email' => $profile->email,
                    'password' => $profile->password,
                    'applications' => $applications
                ]);
                $account->_id = $profile->_id;
                $account->saveModel('accounts', 'accounts');
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
