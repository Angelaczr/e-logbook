<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Pemakaian: `role:manager,super_admin`.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(
            fn (string $role): UserRole => UserRole::tryFrom($role)
                ?? throw new InvalidArgumentException("Role tidak dikenal pada middleware: {$role}"),
            $roles,
        );

        $user = $request->user();

        abort_unless($user !== null && $user->is_active && $user->hasRole(...$allowed), 403);

        return $next($request);
    }
}
