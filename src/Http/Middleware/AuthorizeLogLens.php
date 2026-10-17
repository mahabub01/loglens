<?php

namespace MehrabHossain\LogLens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MehrabHossain\LogLens\LogLens;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeLogLens
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! LogLens::check($request)) {
            abort(403, 'Unauthorized access to LogLens.');
        }

        return $next($request);
    }
}
