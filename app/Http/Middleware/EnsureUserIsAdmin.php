<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akses ditolak. Anda memerlukan hak administrator untuk mengakses sumber daya ini.',
                ], 403);
            }
            abort(403, 'Akses ditolak. Anda memerlukan hak administrator.');
        }

        return $next($request);
    }
}
