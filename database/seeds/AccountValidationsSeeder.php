<?php

namespace {

    use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\Config;

    class AccountValidationsSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            $database = Config::get('database.connections.'.Config::get('database.default').'.database');
            Config::set('database.connections.mongodb.database', 'accounts');
            $defaultDb = Config::get('database.default');
            DB::purge($defaultDb);
            DB::connection($defaultDb);

            DB::collection('validations')->delete();

            // Insert records into validations collection
            DB::collection('validations')->insert(
                [
                    [
                        'fields' => [
                            'name' => 'required|regex:/\\w+ \\w+/',
                            'password' => 'required|min:8',
                            'email' => 'required|email|unique:accounts',
                            'slack' => 'alpha_dash',
                            'trello' => 'alpha_dash',
                            'github' => 'alpha_dash'
                        ],
                        'messages' => [
                            'name.regex' => 'Full name needed, at least 2 words.'
                        ],
                        'resource' => 'accounts',
                        'acl' => [
                            'standard' => [
                                'editable' => [
                                    'name',
                                    'password',
                                    'email',
                                    'slack',
                                    'trello',
                                    'github'
                                ],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => false
                            ],
                            'guest' => [
                                'editable' => [],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => true
                            ]
                        ]
                    ],
                    [
                        'fields' => [
                            'name' => 'required|regex:/\\w+ \\w+/',
                            'email' => 'required|email|unique:profiles',
                            'slack' => 'alpha_dash',
                            'trello' => 'alpha_dash',
                            'github' => 'alpha_dash',
                            'xp' => 'numeric',
                            'xp_id' => 'alpha_num',
                            'active' => 'boolean',
                            'valid' => 'boolean',
                            'employeeRole' => 'string',
                            'employee' => 'boolean',
                            'skills' => 'array',
                            'minimumsMissed' => 'integer'
                        ],
                        'messages' => [
                            'name.regex' => 'Full name needed, at least 2 words.'
                        ],
                        'resource' => 'profiles',
                        'acl' => [
                            'standard' => [
                                'editable' => [
                                    'name',
                                    'email',
                                    'slack',
                                    'trello',
                                    'github',
                                    'active',
                                    'valid',
                                    'skills'
                                ],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => true
                            ],
                            'guest' => [
                                'editable' => [],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => true
                            ],
                            'accountant' => [
                                'editable' => [
                                    'name',
                                    'password',
                                    'email',
                                    'slack',
                                    'trello',
                                    'github',
                                    'active',
                                    'valid',
                                    'skills'
                                ],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => false
                            ],
                        ]
                    ],
                ]
            );

            Config::set('database.connections.mongodb.database', $database);
            $defaultDb = Config::get('database.default');
            DB::purge($defaultDb);
            DB::connection($defaultDb);
        }
    }
}
