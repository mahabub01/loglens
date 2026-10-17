<?php

namespace MehrabHossain\LogLens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MehrabHossain\LogLens\LogLens;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogLensIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! LogLens::isEnabled()) {
            abort(404);
        }

        return $next($request);
    }
}
