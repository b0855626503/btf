<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FilterIps
{
    public $whitelist = [
        '52.77.44.100',
        '13.228.131.58',
        '54.179.213.198',
        '54.179.77.241',
        '52.74.201.149',
        '184.22.224.185'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->ip(), $this->whitelist)) {
            return $next($request);
        }

        abort(403);
    }
}
