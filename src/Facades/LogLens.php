<?php

namespace MehrabHossain\LogLens\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void auth(\Closure $callback)
 * @method static void destructiveAuth(\Closure $callback)
 * @method static void resetAuth()
 * @method static bool check(mixed $request = null)
 * @method static bool checkDestructive(mixed $request = null)
 * @method static bool isEnabled()
 * @method static string path()
 * @method static string version()
 * @method static array scriptVariables()
 *
 * @see \MehrabHossain\LogLens\LogLens
 */
class LogLens extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'loglens';
    }
}
