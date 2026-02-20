<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotSuspended
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        if ($user->isCurrentlySuspended()) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => $this->buildSuspensionMessage($user),
                'suspended_until' => $user->suspended_until?->toIso8601String(),
            ], 423);
        }

        if ($user->is_suspended && $user->suspended_until && $user->suspended_until->isPast()) {
            $user->forceFill([
                'is_suspended' => false,
                'suspended_until' => null,
                'suspension_reason' => null,
            ])->save();
        }

        return $next($request);
    }

    private function buildSuspensionMessage($user): string
    {
        if ($user->suspended_until) {
            return 'Your account is suspended until ' . $user->suspended_until->toDayDateTimeString() . '.';
        }

        return 'Your account is suspended. Contact an administrator.';
    }
}

