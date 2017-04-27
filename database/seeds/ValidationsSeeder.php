<?php

namespace {

    use Illuminate\Database\Seeder;

    class ValidationsSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            // Delete seeded records from validations collection
            DB::collection('validations')->where('resource', 'profiles')->delete();
            DB::collection('validations')->where('resource', 'projects')->delete();
            DB::collection('validations')->where('resource', 'sprints')->delete();
            DB::collection('validations')->where('resource', 'tasks')->delete();
            DB::collection('validations')->where('resource', 'xp')->delete();
            DB::collection('validations')->where('resource', 'comments')->delete();
            DB::collection('validations')->where('resource', 'uploads')->delete();
            DB::collection('validations')->where('resource', 'applications')->delete();

            DB::collection('validations')->delete();

            // Insert records into validations collection
            DB::collection('validations')->insert(
                [
                    [
                        'fields' => [
                            'name' => 'required|regex:/\\w+ \\w+/',
                            'password' => 'required|min:8',
                            'email' => 'required|email|unique:profiles',
                            'applications' => 'array'
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
                                    'applications'
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
                    [
                        'fields' => [
                            'name' => 'required|string',
                            'description' => 'string',
                            'start' => 'required|date_format:U',
                            'end' => 'required|date_format:U',
                            'price' => 'numeric',
                            'acceptedBy' => 'alpha_num',
                            'sprints' => 'array',
                            'members' => 'array',
                            'isComplete' => 'boolean',
                            'isInternal' => 'boolean',
                            'projectCommentsId' => 'alpha_num',
                            'ownerId' => 'alpha_num'
                        ],
                        'resource' => 'projects',
                        'acl' => [
                            'standard' => [
                                'editable' => [],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => false,
                                'updateOwn' => true
                            ]
                        ]
                    ],
                    [
                        'fields' => [
                            'records' => 'array',
                            'ownerId' => 'alpha_num'
                        ],
                        'resource' => 'comments',
                        'acl' => [
                            'standard' => [
                                'editable' => [],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => true,
                                'updateOwn' => true
                            ]
                        ]
                    ],
                    [
                        'fields' => [
                            'records' => 'array'
                        ],
                        'resource' => 'xp',
                        'acl' => [
                            'standard' => [
                                'editable' => [],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => false
                            ]
                        ]
                    ],
                    [
                        'fields' => [
                            'title' => 'string',
                            'start' => 'date_format:U',
                            'end' => 'date_format:U',
                            'tasks' => 'array',
                            'ownerId' => 'alpha_num',
                            'price' => 'numeric'
                        ],
                        'resource' => 'sprints',
                        'acl' => [
                            'standard' => [
                                'editable' => [],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => false,
                                'updateOwn' => true
                            ]
                        ]
                    ],
                    [
                        'fields' => [
                            'sprint_id' => 'alpha_num',
                            'project_id' => 'alpha_num',
                            'title' => 'required|string',
                            'owner' => 'alpha_num',
                            'xp' => 'required|numeric',
                            'payout' => 'required|numeric',
                            'estimatedHours' => 'required|numeric',
                            'due_date' => 'date_format:U',
                            'description' => 'string',
                            'submitted_for_qa' => 'boolean',
                            'passed_qa' => 'boolean',
                            'task_history' => 'array',
                            'ready' => 'boolean',
                            'paused' => 'boolean',
                            'skillset' => 'required|array',
                            'complexity' => 'integer',
                            'priority' => 'string',
                            'ownerId' => 'alpha_num',
                            'commentsId' => 'alpha_num',
                            'watchers' => 'array',
                            'blocked' => 'boolean'
                        ],
                        'resource' => 'tasks',
                        'acl' => [
                            'standard' => [
                                'editable' => [
                                    'submitted_for_qa',
                                    'owner',
                                    'paused',
                                    'task_history',
                                    'blocked',
                                    'commentsId'
                                ],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => false,
                                'updateOwn' => true
                            ]
                        ]
                    ],
                    [
                        'fields' => [
                            'projectId' => 'alpha_num',
                            'name' => 'required|string',
                            'fileUrl' => 'required|string',
                        ],
                        'resource' => 'uploads',
                        'acl' => [
                            'standard' => [
                                'editable' => [],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => false,
                                'updateOwn' => false
                            ]
                        ]
                    ],
                    [
                        'fields' => [
                            'appName' => 'required:string',
                            'dbSlug' => 'required:string'
                        ],
                        'resource' => 'applications',
                        'acl' => [
                            'standard' => [
                                'editable' => [],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => false,
                                'updateOwn' => false
                            ]
                        ]
                    ],
                    [
                        'fields' => [
                            'records' => 'array',
                        ],
                        'resource' => 'vacations',
                        'acl' => [
                            'standard' => [
                                'editable' => [],
                                'GET' => true,
                                'DELETE' => false,
                                'POST' => false,
                                'updateOwn' => true
                            ]
                        ]
                    ],
                ]
            );
        }
    }
}
