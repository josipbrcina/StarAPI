<?php

namespace App\Helpers;

use Carbon\Carbon;

/**
 * Class WorkDays
 * @package App\Helpers
 */
class WorkDays
{
    /**
     * Return list of work days (week days) for month from unix timestamp
     * @param $unixStart
     * @return array
     */
    public static function getWorkDays($unixStart)
    {
        $firstDayOfMonth = Carbon::createFromTimestamp($unixStart)->firstOfMonth();
        $lastDayOfMonth = Carbon::createFromTimestamp($unixStart)->endOfMonth();

        $dates = [];

        for ($date = $firstDayOfMonth; $date->lte($lastDayOfMonth); $date->addDay()) {
            if ($date->isWeekday()) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
    }
}
