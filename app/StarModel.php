<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

/**
 * Class StarModel
 * @package App
 */
class StarModel extends Eloquent
{
    /**
     * Store user ID into model upon model creation
     */
    public static function boot()
    {
        parent::boot();
        if (!\Auth::guest()) {
            static::creating(function ($model) {
                $userId = \Auth::user()->id;
                $model->ownerId = $userId;
            });
        }
    }
}
