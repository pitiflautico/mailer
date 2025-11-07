<?php

namespace App\Http\Middleware;

use App\Services\IpReputationService;
use App\Services\SpamFilterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AntiSpamMiddleware
{
    public function __construct(
        protected IpReputationService $ipReputationService,
        protected SpamFilterService $spamFilterService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check IP reputation
        $ipAddress = $request->ip();
        if (!$this->ipReputationService->canSend($ipAddress)) {
            return response()->json([
                'success' => false,
                'error' => 'Your IP address has been blocked due to suspicious activity',
                'code' => 'IP_BLOCKED',
            ], 403);
        }

        // Check request rate
        if ($this->isRateLimitExceeded($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'code' => 'RATE_LIMIT_EXCEEDED',
            ], 429);
        }

        return $next($request);
    }

    /**
     * Check if rate limit is exceeded.
     */
    protected function isRateLimitExceeded(Request $request): bool
    {
        // Implement rate limiting logic
        // This is a simplified version
        $key = 'rate_limit:' . $request->ip();
        $limit = 60; // requests per minute

        return cache()->remember($key, 60, function () use ($limit) {
            return 0;
        }) >= $limit;
    }
}
