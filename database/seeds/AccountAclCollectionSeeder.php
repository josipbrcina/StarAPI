<?php

namespace {

    use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\Config;

    class AccountAclCollectionSeeder extends Seeder
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

            // Delete previously seeded records from acl collection
            DB::collection('acl')->where('name', 'guest')->delete();
            DB::collection('acl')->where('name', 'standard')->delete();
            DB::collection('acl')->where('name', 'accountant')->delete();

            // Insert records into acl collection
            DB::collection('acl')->insert(
                [
                    [
                        'name' => 'standard',
                        'allows' => [
                            'GET' => [
                                "api/v1/app/{appName}/configuration",
                                "api/v1/app/{appName}/accounts",
                                "api/v1/app/{appName}/accounts/{accounts}",
                            ],
                            'PUT' => [
                                'api/v1/app/{appName}/accounts/{accounts}'
                            ],
                            'POST' => [
                                'api/v1/app/{appName}/application/create'
                            ],
                            'PATCH' => [
                                'api/v1/app/{appName}/accounts/{accounts}'
                            ]
                        ]
                    ],
                    [
                        'name' => 'guest',
                        'allows' => [
                            'POST' => [
                                'api/v1/app/{appName}/register',
                                'api/v1/app/{appName}/login'
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
