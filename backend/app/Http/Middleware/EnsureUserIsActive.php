<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->status !== UserStatus::Active) {
            throw new DomainException('Your account is suspended.', 'account_suspended', 403);
        }

        return $next($request);
    }
}
