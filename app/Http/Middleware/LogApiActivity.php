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

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((hrtime(true) - $start) / 1_000_000);

        if ($request->is('up')) {
            return $response;
        }

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

        $payload = [
            'method'      => $request->method(),
            'path'        => '/' . ltrim($request->path(), '/'),
            'full_url'    => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'query'       => $request->query(),
            'body'        => $requestBody,
            'user_agent'  => $request->userAgent(),
        ];

        $segments = explode('/', trim($request->path(), '/'));
        $logicalTable = 'api:' . ($segments[1] ?? 'general');

        $curl = sprintf(
            "curl -X %s '%s'",
            $request->method(),
            $request->fullUrl()
        );

        ActivityLog::create([
        'user_id'     => optional($request->user())->id,
        'actor_type'  => $request->user() ? 'user' : 'guest',
        'action'      => 'api_hit',
        'status_code' => $response->getStatusCode(),
        'duration_ms' => $durationMs,

        'table'       => $logicalTable,
        'row_id'      => null, 

        'description' => sprintf(
            '%s %s (%d) %dms',
            $request->method(),
            '/' . ltrim($request->path(), '/'),
            $response->getStatusCode(),
            $durationMs
        ),

        'payload'     => $payload,
        'curl'        => $curl,
        'request_id'  => $requestId,
        'ip_address'  => $request->ip(),
    ]);


        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
