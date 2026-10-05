<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guests may look around but never change anything: every write request and
 * every page that only exists to fill in a form is turned away.
 */
class ReadOnlyGuest
{
    /** GET pages that are just forms or personal tools. */
    private const BLOCKED_PAGES = [
        'exercises/create',
        'exercises/edit/*',
        'competitions/create',
        'competitions/edit/*',
        'competitions/*/score',
        'training/create',
        'training/players/search',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user?->isGuest() || $request->routeIs('logout')) {
            return $next($request);
        }

        $isWrite = !$request->isMethodSafe();
        if (!$isWrite && !$request->is(...self::BLOCKED_PAGES)) {
            return $next($request);
        }

        $message = 'Guests can look around but not save anything. Create a free account to do that.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return ($isWrite ? back() : redirect()->route('dashboard'))->with('error', $message);
    }
}
