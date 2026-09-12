<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiRequestLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID') ?? (string) Str::uuid();
        $startTime = microtime(true);

        $request->attributes->set('request_id', $requestId);

        $response = $next($request);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->logRequest($request, $response, $requestId, $durationMs);

        if (! $response->headers->has('X-Request-ID')) {
            $response->headers->set('X-Request-ID', $requestId);
        }

        return $response;
    }

    private function logRequest(Request $request, Response $response, string $requestId, float $durationMs): void
    {
        $status = $response->getStatusCode();
        $user = $request->user();

        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status' => $status,
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $user?->id,
            'resident_id' => $user?->resident_id,
        ];

        $level = $this->logLevel($status);

        Log::$level('API request completed', $context);
    }

    private function logLevel(int $status): string
    {
        if ($status >= 500) {
            return 'error';
        }

        if (in_array($status, [400, 401, 403, 409, 422, 429])) {
            return 'warning';
        }

        return 'info';
    }
}
