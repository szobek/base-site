<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;
        $value = $role instanceof UserRole ? $role->value : $role;

        if (! in_array($value, $roles, true)) {
            abort(403, 'Nincs jogosultságod ehhez a művelethez.');
        }

        return $next($request);
    }
}
