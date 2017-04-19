<?php

namespace App\Helpers;

/**
 * Class Time
 * @package App\Helpers
 */
class InputHandler
{
    /**
     * Returns integer of timestamp if sent in seconds or microseconds, throws exception otherwise
     *
     * @param $input
     * @return int
     * @throws \Exception
     */
    public static function getUnixTimestamp($input)
    {
        if (strlen((string)$input) === 10) {
            return (int)$input;
        } elseif (strlen((string)$input) === 13) {
            return (int)substr((string)$input, 0, -3);
        }

        throw new \Exception('Unrecognized unix timestamp format');
    }

    /**
     * @param $float
     * @return float
     * @throws \Exception
     */
    public static function getFloat($float)
    {
        if (is_float($float)) {
            return $float;
        }

        if ((float)$float == $float) {
            return (float)$float;
        }

        throw new \Exception('Input not a float.');
    }

    /**
     * @param $input
     * @return int
     * @throws \Exception
     */
    public static function getInteger($input)
    {
        if (is_numeric($input) === true) {
            return (int)$input;
        }

        throw new \Exception('Input not an integer.');
    }

    /**
     * Helper method to round the float correctly
     *
     * @param $float
     * @param $position
     * @param $startAt
     * @return mixed
     */
    public static function roundFloat($float, $position, $startAt)
    {
        if ($position < $startAt) {
            $startAt--;
            $newFloat = round($float, $startAt);
            return self::roundFloat($newFloat, $position, $startAt);
        }

        return $float;
    }
}
