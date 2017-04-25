<?php

namespace App\Exceptions;

/**
 * Class UserInputException
 * @package App\Exceptions
 */
class UserInputException extends \Exception
{
    /**
     * UserInputException constructor.
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message, $code = 400, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
