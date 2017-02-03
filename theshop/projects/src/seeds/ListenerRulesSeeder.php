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
                        'listeners' => []
                    ],
                    [
                        'resource' => 'tasks',
                        'event' => 'update',
                        'listeners' => [
                            'TheShop\Projects\Events\TaskUpdateSlackNotify' => [
                                'TheShop\Projects\Listeners\TaskUpdateSlackNotification',
                            ],
                            'TheShop\Projects\Events\ModelUpdate' => [
                                'TheShop\Projects\Listeners\TaskUpdateXP',
                            ],
                            'TheShop\Projects\Events\TaskClaim' => [
                                'TheShop\Projects\Listeners\TaskClaim'
                            ],
                            'TheShop\Projects\Events\TaskSettingStatus' => [
                                'TheShop\Projects\Listeners\TaskSettingStatus'
                            ],
                            'TheShop\Projects\Events\TaskFinishedEarly' => [
                                'TheShop\Projects\Listeners\TaskFinishedEarly'
                            ],
                            'TheShop\Projects\Events\TaskStatusHistory' => [
                                'TheShop\Projects\Listeners\TaskStatusHistory'
                            ],
                            'TheShop\Projects\Events\GenericModelHistory' => [
                                'TheShop\Projects\Listeners\GenericModelHistory'
                            ],
                        ]
                    ],
                    [
                        'resource' => 'projects',
                        'event' => 'update',
                        'listeners' => [
                            'TheShop\Projects\Events\ProjectMembers' => [
                                'TheShop\Projects\Listeners\ProjectMembers'
                            ]
                        ]
                    ],
                ]
            );
        }
    }
}
