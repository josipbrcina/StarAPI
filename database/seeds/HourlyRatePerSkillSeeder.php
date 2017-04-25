<?php

namespace {

    use Illuminate\Database\Seeder;

    /**
     * Class HourlyRatePerSkillSeeder
     */
    class HourlyRatePerSkillSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            DB::collection('hourly-rates')->delete();
            DB::collection('hourly-rates')->insert(
                [
                    [
                        'hourlyRates' => [
                            'PHP' => 500,
                            'React' => 500,
                            'DevOps' => 500,
                            'Node' => 500,
                            'Planning' => 500,
                            'Management' => 500
                        ]
                    ]
                ]
            );
        }
    }
}
