<?php

namespace MehrabHossain\LogLens\Contracts;

interface AuthorizerInterface
{
    /**
     * Determine if the given request is authorized to view LogLens.
     *
     * @param  \Illuminate\Http\Request|mixed  $request
     * @return bool
     */
    public function authorizes(mixed $request): bool;

    /**
     * Determine if the given request is authorized to execute destructive LogLens actions.
     *
     * @param  \Illuminate\Http\Request|mixed  $request
     * @return bool
     */
    public function authorizesDestructive(mixed $request): bool;
}
