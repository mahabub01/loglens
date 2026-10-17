<?php

namespace MehrabHossain\LogLens\Support;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use MehrabHossain\LogLens\Contracts\AuthorizerInterface;
use MehrabHossain\LogLens\LogLens;
use ReflectionFunction;

class Authorizer implements AuthorizerInterface
{
    public function __construct(
        protected Application $app,
        protected Gate $gate
    ) {
    }

    /**
     * Determine if the given request is authorized to view LogLens.
     */
    public function authorizes(mixed $request): bool
    {
        $request = $request instanceof Request ? $request : request();

        // 1. Check custom callback registered via LogLens::auth(...)
        $authCallback = LogLens::$authUsing;
        if ($authCallback instanceof Closure) {
            return (bool) $authCallback($request);
        }

        // 2. Check registered Gate if defined
        $gateName = (string) config('loglens.auth.gate', 'viewLogLens');
        if ($this->gate->has($gateName)) {
            return $this->evaluateGate($gateName, $request);
        }

        // 3. Fallback for local development environment if allowed
        if ($this->app->environment('local') && config('loglens.auth.allow_local_by_default', true)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the given request is authorized to execute destructive LogLens actions.
     */
    public function authorizesDestructive(mixed $request): bool
    {
        // Must have destructive actions explicitly enabled in configuration
        if (! config('loglens.cleanup.allow_destructive_actions', false)) {
            return false;
        }

        $request = $request instanceof Request ? $request : request();

        // 1. Check custom destructive callback registered via LogLens::destructiveAuth(...)
        $destructiveCallback = LogLens::$destructiveAuthUsing;
        if ($destructiveCallback instanceof Closure) {
            return (bool) $destructiveCallback($request);
        }

        // 2. Check registered destructive Gate if defined
        $destructiveGate = (string) config('loglens.auth.destructive_gate', 'manageLogLens');
        if ($this->gate->has($destructiveGate)) {
            return $this->evaluateGate($destructiveGate, $request);
        }

        return false;
    }

    /**
     * Safely evaluate a registered Gate ability across authenticated users,
     * optional user signatures, and zero-parameter closures.
     */
    protected function evaluateGate(string $gateName, ?Request $request): bool
    {
        if (! $this->gate->has($gateName)) {
            return false;
        }

        $user = $request?->user();

        // If user is authenticated, evaluate gate for that specific user
        if ($user !== null) {
            return (bool) $this->gate->forUser($user)->check($gateName, [$request]);
        }

        // If no user is authenticated, check standard gate check (e.g. nullable $user parameter)
        if ($this->gate->check($gateName, [$request])) {
            return true;
        }

        // Support zero-parameter closures for guests (e.g. Gate::define('viewLogLens', fn () => true))
        if (method_exists($this->gate, 'abilities')) {
            $abilities = $this->gate->abilities();
            $callback = $abilities[$gateName] ?? null;

            if ($callback instanceof Closure) {
                $reflection = new ReflectionFunction($callback);
                if ($reflection->getNumberOfParameters() === 0) {
                    return (bool) $callback();
                }
            }
        }

        return false;
    }
}
