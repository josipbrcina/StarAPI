<?php

namespace {

    use Illuminate\Database\Seeder;

    class ListenerRulesSeeder extends Seeder
    {
        /**
         * Run the database seeds.
         *
         * @return void
         */
        public function run()
        {
            DB::collection('listener-rules')->delete();
            DB::collection('listener-rules')->insert(
                [
                    [
                        'resource' => 'tasks',
                        'event' => 'create',
                        'listeners' => [
                            'App\Events\TaskStatusTimeCalculation' => [
                                'App\Listeners\TaskStatusTimeCalculation'
                            ]
                        ]
                    ],
                    [
                        'resource' => 'tasks',
                        'event' => 'update',
                        'listeners' => [
                            'App\Events\TaskUpdateSlackNotify' => [
                                'App\Listeners\TaskUpdateSlackNotification',
                            ],
                            'App\Events\TaskClaim' => [
                                'App\Listeners\TaskClaim'
                            ],
                            'App\Events\TaskSettingStatus' => [
                                'App\Listeners\TaskSettingStatus'
                            ],
                            'App\Events\TaskBlockedNotifyProjectOwner' => [
                                'App\Listeners\TaskBlockedNotifyProjectOwner'
                            ],
                            'App\Events\TaskStatusHistory' => [
                                'App\Listeners\TaskStatusHistory'
                            ],
                            'App\Events\TaskStatusTimeCalculation' => [
                                'App\Listeners\TaskStatusTimeCalculation'
                            ],
                            'App\Events\ModelUpdate' => [
                                'App\Listeners\TaskUpdateXP',
                            ],
                            'App\Events\TaskFinishedEarly' => [
                                'App\Listeners\TaskFinishedEarly'
                            ],
                            'App\Events\GenericModelHistory' => [
                                'App\Listeners\GenericModelHistory'
                            ],
                        ]
                    ],
                    [
                        'resource' => 'projects',
                        'event' => 'update',
                        'listeners' => [
                            'App\Events\ProjectMembers' => [
                                'App\Listeners\ProjectMembers'
                            ]
                        ]
                    ],
                    [
                        'resource' => 'projects',
                        'event' => 'delete',
                        'listeners' => [
                            'App\Events\ProjectDelete' => [
                                'App\Listeners\ProjectDelete'
                            ]
                        ]
                    ],
                    [
                        'resource' => 'projects_deleted',
                        'event' => 'delete',
                        'listeners' => [
                            'App\Events\ProjectDelete' => [
                                'App\Listeners\ProjectDelete'
                            ]
                        ]
                    ],
                    [
                        'resource' => 'projects',
                        'event' => 'archive',
                        'listeners' => [
                            'App\Events\ProjectArchive' => [
                                'App\Listeners\ProjectArchive'
                            ]
                        ]
                    ],
                    [
                        'resource' => 'projects_archived',
                        'event' => 'archive',
                        'listeners' => [
                            'App\Events\ProjectArchive' => [
                                'App\Listeners\ProjectArchive'
                            ]
                        ]
                    ]
                ]
            );
        }
    }
}
