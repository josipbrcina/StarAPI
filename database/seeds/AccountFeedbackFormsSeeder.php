<?php
namespace {

    use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\Config;

    class AccountFeedbackFormsSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            $database = Config::get('database.connections.' . Config::get('database.default') . '.database');
            Config::set('database.connections.mongodb.database', 'accounts');
            $defaultDb = Config::get('database.default');
            DB::purge($defaultDb);
            DB::connection($defaultDb);


            DB::collection('feedback-forms')->delete();
            DB::collection('feedback-forms')->insert(
                [
                    [
                        'fields' => [
                            [
                                'label' => 'How satisfied with the job are you?',
                                'required' => true,
                                'inputs' => [
                                    [
                                        'inputType' => 'radio',
                                        'name' => 'satisfaction',
                                        'value' => '1'
                                    ],
                                    [
                                        'inputType' => 'radio',
                                        'name' => 'satisfaction',
                                        'value' => '2'
                                    ],
                                    [
                                        'inputType' => 'radio',
                                        'name' => 'satisfaction',
                                        'value' => '3'
                                    ],
                                    [
                                        'inputType' => 'radio',
                                        'name' => 'satisfaction',
                                        'value' => '4'
                                    ],
                                    [
                                        'inputType' => 'radio',
                                        'name' => 'satisfaction',
                                        'value' => '5'
                                    ],
                                ]
                            ],
                            [
                                'label' => 'Leave comments and feedback',
                                'required' => false,
                                'inputs' => [
                                    [
                                        'inputType' => 'textarea',
                                        'name' => 'feedback',
                                        'value' => ''
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            );

            Config::set('database.connections.mongodb.database', $database);
            $defaultDb = Config::get('database.default');
            DB::purge($defaultDb);
            DB::connection($defaultDb);
        }
    }
}
