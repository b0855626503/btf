<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class AuthenticateUser extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        return redirect()->route('customer.session.index');
    }

    protected function authenticate($request,  $guards)
    {
        if (empty($guards)) {
            $guards = ['customer'];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return $this->auth->shouldUse($guard);
            }
        }

//        dd('a');

        $this->unauthenticated($request, $guards);
    }
}
