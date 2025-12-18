<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
        $start = hrtime(true);

        $response = $next($request);

        if ($request->is('up')) {
            return $response;
        }

        $durationMs = (int) round((hrtime(true) - $start) / 1_000_000);

        $query = $request->query();
        $qs = $query ? http_build_query($query) : '';
        $safeUrl = $request->url() . ($qs ? ('?' . $qs) : '');

        $safeHeaders = [
            'Accept' => $request->header('Accept') ?? 'application/json',
            'Content-Type' => $request->header('Content-Type') ?? 'application/json',
            'X-Request-Id' => $requestId,
        ];

        if ($request->hasHeader('Authorization')) {
            $safeHeaders['Authorization'] = 'Bearer ***REDACTED***';
        }

        $curlHeaders = collect($safeHeaders)->map(
            fn ($v, $k) => "-H " . escapeshellarg($k . ': ' . $v)
        )->implode(' ');

        $requestBody = [];
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            $requestBody = $request->isJson()
                ? (array) $request->json()->all()
                : (array) $request->all();

            $requestBody = Arr::except($requestBody, [
                'password',
                'password_confirmation',
                'token',
                'access_token',
                'refresh_token',
            ]);
        }

        $curlBody = '';
        if (!empty($requestBody)) {
            $curlBody = "-d " . escapeshellarg(json_encode($requestBody, JSON_UNESCAPED_SLASHES));
        }

        $curl = trim(sprintf(
            "curl -X %s %s '%s' %s",
            $request->method(),
            $curlHeaders,
            $safeUrl,
            $curlBody
        ));

        $payload = null;
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            $payload = [
                'path' => '/' . ltrim($request->path(), '/'),
                'full_url' => $safeUrl,
                'query' => $query,
                'body' => $requestBody,
                'user_agent' => $request->userAgent(),
            ];
        }

        $segments = explode('/', trim($request->path(), '/'));
        $logicalTable = 'api:' . ($segments[1] ?? 'general');

        ActivityLog::create([
            'user_id' => optional($request->user())->id,
            'actor_type' => $request->user() ? 'user' : 'guest',
            'action' => 'api_hit',
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'table' => $logicalTable,
            'row_id' => null,
            'description' => sprintf(
                '%s %s (%d) %dms',
                $request->method(),
                '/' . ltrim($request->path(), '/'),
                $response->getStatusCode(),
                $durationMs
            ),
            'payload' => $payload,
            'curl' => $curl,
            'request_id' => $requestId,
            'ip_address' => $request->ip(),
        ]);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
