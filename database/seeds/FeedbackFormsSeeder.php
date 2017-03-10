<?php

namespace {

    use Illuminate\Database\Seeder;

    class FeedbackFormsSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
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
        }
    }
}
