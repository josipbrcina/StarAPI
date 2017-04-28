<?php

namespace {

    use Illuminate\Database\Seeder;

    /**
     * Class DatabaseSeeder
     */
    class DatabaseSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            $this->call(AclCollectionSeeder::class);
            $this->call(ValidationsSeeder::class);
            $this->call(ListenerRulesSeeder::class);
            $this->call(AdapterRulesSeeder::class);
            $this->call(UserRolesSeeder::class);
            $this->call(FeedbackFormsSeeder::class);
            $this->call(HourlyRatePerSkillSeeder::class);
            $this->call(AccountValidationsSeeder::class);
            $this->call(AccountAclCollectionSeeder::class);
            $this->call(AccountFeedbackFormsSeeder::class);
            $this->call(ApplicationRegistrationSeeder::class);
        }
    }
}
