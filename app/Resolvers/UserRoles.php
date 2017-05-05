<?php

namespace App\Resolvers;

use App\GenericModel;

class UserRoles
{
    public static function getRoles()
    {
        $userRoles = GenericModel::whereTo('user-roles')
            ->first();

        $resolvedRoles = array_map('strtolower', $userRoles->userRoles);

        return $resolvedRoles;
    }
}
