<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson()) {
                abort(401, 'Unauthenticated.');
            }

            return redirect()->guest(route('login'));
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                abort(403, 'Forbidden.');
            }

            return redirect()
                ->route($user->isAdmin() ? 'dashboard' : 'sales.create')
                ->withErrors(['access' => __('pos.access_denied')]);
        }

        return $next($request);
    }
}
