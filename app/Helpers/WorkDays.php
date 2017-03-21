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
     * Return list of work days (week days) for current month
     * Class WorkDays
     * @package App\Helpers
     */
    public static function getWorkDays()
    {
        $firstDayOfMonth = Carbon::now()->firstOfMonth();
        $lastDayOfMonth = Carbon::now()->endOfMonth();

        $dates = [];

        for ($date = $firstDayOfMonth; $date->lte($lastDayOfMonth); $date->addDay()) {
            if ($date->isWeekday()) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
    }
}
