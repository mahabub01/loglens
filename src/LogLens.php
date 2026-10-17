<?php

namespace MehrabHossain\LogLens;

use Closure;
use Illuminate\Http\Request;
use MehrabHossain\LogLens\Contracts\AuthorizerInterface;

class LogLens
{
    /**
     * Package version.
     */
    public const VERSION = '1.0.0';

    /**
     * The callback used to authorize dashboard access.
     */
    public static ?Closure $authUsing = null;

    /**
     * The callback used to authorize destructive operations.
     */
    public static ?Closure $destructiveAuthUsing = null;

    /**
     * Set the authorization callback for viewing LogLens.
     */
    public static function auth(Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /**
     * Set the authorization callback for destructive LogLens actions.
     */
    public static function destructiveAuth(Closure $callback): void
    {
        static::$destructiveAuthUsing = $callback;
    }

    /**
     * Reset authorization callbacks (useful for testing).
     */
    public static function resetAuth(): void
    {
        static::$authUsing = null;
        static::$destructiveAuthUsing = null;
    }

    /**
     * Determine if the given request can access LogLens.
     */
    public static function check(mixed $request = null): bool
    {
        $authorizer = app(AuthorizerInterface::class);

        return $authorizer->authorizes($request ?? request());
    }

    /**
     * Determine if the given request can perform destructive LogLens actions.
     */
    public static function checkDestructive(mixed $request = null): bool
    {
        $authorizer = app(AuthorizerInterface::class);

        return $authorizer->authorizesDestructive($request ?? request());
    }

    /**
     * Check if the package is enabled.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('loglens.enabled', false);
    }

    /**
     * Get the configured route prefix/path.
     */
    public static function path(): string
    {
        return (string) config('loglens.path', 'loglens');
    }

    /**
     * Get the package version.
     */
    public static function version(): string
    {
        return static::VERSION;
    }

    /**
     * Get default JavaScript variables for the dashboard view.
     */
    public static function scriptVariables(): array
    {
        return [
            'path' => static::path(),
            'version' => static::VERSION,
            'enabled' => static::isEnabled(),
            'environment' => app()->environment(),
            'csrfToken' => csrf_token(),
        ];
    }
}
