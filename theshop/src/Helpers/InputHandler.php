<?php

namespace TheShop\Src\Helpers;

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
        if (strlen((string) $input) === 10) {
            return (int) $input;
        } elseif (strlen((string) $input) === 13) {
            return (int) substr((string) $input, 0, -3);
        }

        throw new \Exception('Unrecognized unix timestamp format');
    }

    public static function getInteger($input)
    {
        if (is_numeric($input) === true) {
            return (int) $input;
        }

        throw new \Exception('Input not an integer.');
    }
}
