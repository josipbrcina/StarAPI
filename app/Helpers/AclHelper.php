<?php

namespace App\Helpers;

use App\GenericModel;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Class AclHelper
 * @package App\Helpers
 */
class AclHelper
{
    /**
     * Get user ACL role
     *
     * @param $user
     * @return mixed
     */
    public static function getAcl($user)
    {
        if (!$user) {
            // If there's no current user, set guest role ACL
            $roleName = Config::get('sharedSettings.internalConfiguration.guestRole');
            $acl = GenericModel::whereTo('acl')
                ->where('name', '=', $roleName)
                ->first();
        } elseif ($user->aclId) {
            // If special role set for current user, use that
            $acl = GenericModel::whereTo('acl')
                ->where('_id', '=', $user->aclId)
                ->first();
        } else {
            // Assume standard user role if no special role is set and is current user is not guest
            $roleName = Config::get('sharedSettings.internalConfiguration.defaultRole');
            $acl = GenericModel::whereTo('acl')
                ->where('name', '=', $roleName)
                ->first();
        }

        //check if user has role
        if (!$acl instanceof GenericModel) {
            throw new MethodNotAllowedHttpException([], 'Insufficient permissions.');
        }

        return $acl;
    }
}
