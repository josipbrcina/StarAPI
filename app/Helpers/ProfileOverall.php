<?php

namespace App\Helpers;

use App\Profile;
use App\GenericModel;

/**
 * Class ProfileOverall
 * @package App\Helpers
 */
class ProfileOverall
{
    /**
     * Get profile overall record
     * @param Profile $profile
     * @return GenericModel
     */
    public static function getProfileOverallRecord(Profile $profile)
    {
        $profileOverallRecord = GenericModel::whereTo('profile_overall')->find($profile->id);
        if (!$profileOverallRecord) {
            $profileOverallRecord = new GenericModel([
                'totalEarned' => 0,
                'totalCost' => 0,
                'profit' => 0
            ]);
            $profileOverallRecord->_id = $profile->id;
            $profileOverallRecord->saveModel('profile_overall');
        }

        return $profileOverallRecord;
    }
}
