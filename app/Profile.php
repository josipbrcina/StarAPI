<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

/**
 * Class Profile
 * @package App
 */
class Profile extends StarModel implements AuthenticatableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'github',
        'trello',
        'slack',
        'valid',
        'employee',
        'xp',
        'active',
        'xp_id',
        'admin',
        'role',
        'employeeRole',
        'skills',
        'minimumsMissed',
    ];

    /**
     * Save a new model and return the instance.
     * @param $accountId
     * @param array $attributes
     * @return static
     */
    public static function createForAccount($accountId, array $attributes = [])
    {
        $model = new static($attributes);

        $model->_id = $accountId;
        $model->xp = 50;
        $model->role = 'standard';
        $model->minimumsMissed = 0;
        $model->accountActive = true;

        $model->save();

        return $model;
    }
}
