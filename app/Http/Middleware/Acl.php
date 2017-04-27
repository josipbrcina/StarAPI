<?php

namespace App\Http\Middleware;

use App\Helpers\AuthHelper;
use App\Profile;
use Closure;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use App\Helpers\AclHelper;

class Acl
{
    /**
     * Handle an incoming request. Check user route permissions.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $routeUri = $request->route()->getUri();
        $routeMethod = $request->method();

        $user = AuthHelper::getAuthenticatedUser();
        $profile = Profile::find($user->_id);
        if ($profile) {
            $user = $profile;
        }

        if ($user && $user->admin === true) {
            return $next($request);
        }

        $acl = AclHelper::getAcl($user);

        // If there's no ACL defined, presume no permissions
        if (!key_exists($routeMethod, $acl->allows) || !in_array($routeUri, $acl->allows[$routeMethod])) {
            throw new MethodNotAllowedHttpException([], 'Insufficient permissions.');
        }

        return $next($request);
    }
}
