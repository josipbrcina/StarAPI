<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\GenericModel;
use MongoDB\BSON\ObjectID;

/**
 * Class XpDeduction
 * @package App\Console\Commands
 */
class XpDeduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xp:activity:auto-deduct';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check user activity - deduct XP based on user inactivity';

    /**
     * XpDeduction constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $profiles = GenericModel::whereTo('profiles')
        ->all();

        $profileHashMap = [];
        foreach ($profiles as $profile) {
            $profileHashMap[$profile->_id] = $profile;
        }

        $daysChecked = 0;

        do {
            // Set current time of cron start and get all logs for previous 4 days
            $date = new \DateTime();
            $cronTime = $date->format('U');
            $unixNow = $date->format('U') - (24 * 60 * 60 * $daysChecked);
            $unixDayAgo = $unixNow - 24 * 60 * 60;
            $hexNow = dechex($unixNow);
            $hexDayAgo = dechex($unixDayAgo);
            $logs = GenericModel::whereTo('logs')
                ->where('_id', '<', new ObjectID($hexNow . '0000000000000000'))
                ->where('_id', '>=', new ObjectID($hexDayAgo . '0000000000000000'))
                ->get();

            $logHashMap = [];
            foreach ($logs as $log) {
                $logHashMap[$log->id] = $log;
            }

            foreach ($profileHashMap as $user) {
                if (isset($user->banned) && $user->banned === true) {
                    unset($profileHashMap[$user->_id]);
                    continue;
                }

                if (isset($user->active) && $user->active === false) {
                    unset($profileHashMap[$user->_id]);
                    continue;
                }

                if (key_exists($user->_id, $logHashMap)) {
                    $profileHashMap[$user->_id]->lastTimeActivityCheck = $cronTime;
                    $profileHashMap[$user->_id]->save();
                    unset($profileHashMap[$user->_id]);
                    continue;
                }

                if (!key_exists($user->_id, $logHashMap) && $daysChecked === 4 && $user->role === 'standard') {
                    $profile = $profileHashMap[$user->_id];
                    if ($profile->xp - 1 == 0) {
                        $profile->banned = true;
                    }

                    if (!$profile->xp_id) {
                        $userXP = new GenericModel(['records' => []]);
                        $userXP->saveModel('xp');
                        $profile->xp_id = $userXP->_id;
                    } else {
                        $userXP = GenericModel::whereTo('xp')->find($profile->xp_id);
                    }

                    $records = $userXP->records;
                    $records[] = [
                        'xp' => -1,
                        'details' => 'Xp deducted for inactivity.',
                        'timestamp' => (int) ($cronTime . '000') // Microtime
                    ];
                    $userXP->records = $records;
                    $userXP->save();

                    $profile->xp--;
                    $profile->lastTimeActivityCheck = (int) $cronTime;
                    $profile->saveModel('profiles');
                }
            }

            $daysChecked++;
            unset($logHashMap);
        } while (count($profileHashMap) > 0 && $daysChecked < 5);
    }
}
