<?php

namespace App\Http\Middleware;

use App\Jobs\RecordActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class ActivityLogMiddleware
{
    /**
     * @var array<int, string>
     */
    protected array $redactedKeys;

    /**
     * @var array<int, string>
     */
    protected array $skipPaths;

    public function __construct()
    {
        $this->redactedKeys = array_map('strtolower', config('activity-log.redact_keys', []));
        $this->skipPaths = config('activity-log.skip_paths', []);
    }

    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (!config('activity-log.enabled', true) || App::runningInConsole() || $request->isMethod('options')) {
            return;
        }

        foreach ($this->skipPaths as $skip) {
            if ($request->is($skip)) {
                return;
            }
        }

        $payload = $this->buildLogPayload($request, $response);

        if ($payload === null) {
            return;
        }

        RecordActivityLog::dispatch($payload)->afterResponse();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildLogPayload(Request $request, $response): ?array
    {
        $route = $request->route();
        $routeParams = $route ? $route->parameters() : [];

        $requestId = $request->headers->get('X-Request-Id') ?? (string) Str::uuid();
        if (!$request->headers->has('X-Request-Id') && method_exists($response, 'headers')) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        $user = $request->user('api') ?? $request->user();

        $payload = [
            'query' => $this->redactData($request->query()),
            'body' => $this->redactData($this->extractBody($request)),
            'files' => $this->summarizeFiles($request),
        ];

        return [
            'user_id' => $user?->id,
            'actor_type' => $user ? 'user' : 'system',
            'action' => strtolower($request->method()),
            'table' => $this->resolveTableName($request),
            'row_id' => $this->resolveRowId($routeParams),
            'description' => $this->buildDescription($request, $response),
            'payload' => $this->truncatePayload($payload),
            'curl' => $this->buildCurlCommand($request),
            'request_id' => $requestId,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    protected function resolveTableName(Request $request): string
    {
        $uri = $request->route()?->uri() ?? trim($request->path(), '/');
        $segment = Str::before($uri, '/');

        return $segment !== '' ? $segment : 'root';
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    protected function resolveRowId(array $routeParams): ?string
    {
        foreach ($routeParams as $key => $value) {
            if ($this->looksLikeIdKey($key) && (is_scalar($value) || (is_object($value) && method_exists($value, 'getKey')))) {
                return is_object($value) ? (string) $value->getKey() : (string) $value;
            }
        }

        return null;
    }

    protected function looksLikeIdKey(string $key): bool
    {
        $lowerKey = strtolower($key);
        return $lowerKey === 'id' || str_ends_with($lowerKey, '_id');
    }

    protected function buildDescription(Request $request, $response): string
    {
        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;
        $path = $request->path() ?: '/';

        return trim(sprintf('%s %s%s', strtoupper($request->method()), $path, $status ? " ({$status})" : ''));
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractBody(Request $request): array
    {
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) {
            return [];
        }

        $body = $request->all();

        foreach ($request->allFiles() as $key => $files) {
            unset($body[$key]);
        }

        return $body;
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    protected function redactData($data)
    {
        if (is_array($data)) {
            $clean = [];
            foreach ($data as $key => $value) {
                $clean[$key] = in_array(strtolower((string) $key), $this->redactedKeys, true)
                    ? '[redacted]'
                    : $this->redactData($value);
            }
            return $clean;
        }

        if (is_object($data)) {
            return '[object]';
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function summarizeFiles(Request $request): array
    {
        $files = [];
        foreach ($request->allFiles() as $key => $uploaded) {
            if (is_array($uploaded)) {
                $files[$key] = array_map(
                    fn ($file) => $file->getClientOriginalName(),
                    $uploaded
                );
            } else {
                $files[$key] = $uploaded->getClientOriginalName();
            }
        }

        return $files;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function truncatePayload(array $payload): array
    {
        $limit = (int) config('activity-log.max_payload_bytes', 8000);
        $json = json_encode($payload);

        if ($json === false) {
            return ['_error' => 'payload_json_failed'];
        }

        if (strlen($json) <= $limit) {
            return $payload;
        }

        return [
            'query' => $payload['query'],
            'body' => $this->truncateString(json_encode($payload['body']), (int) floor($limit / 2)),
            'files' => $payload['files'],
            '_truncated' => true,
        ];
    }

    protected function buildCurlCommand(Request $request): string
    {
        $method = strtoupper($request->method());
        $url = $request->fullUrl();
        $skipHeaders = array_map('strtolower', config('activity-log.redact_headers', []));
        $parts = ["curl -X {$method} '" . $this->escape($url) . "'"];

        foreach ($request->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $skipHeaders, true)) {
                continue;
            }

            $parts[] = "-H '" . $this->escape($name) . ': ' . $this->escape(implode(', ', $values)) . "'";
        }

        if (!in_array($method, ['GET', 'HEAD'], true)) {
            $body = $request->getContent();

            if ($request->allFiles()) {
                $body = '[file upload omitted]';
            }

            if ($body !== '') {
                $parts[] = "--data '" . $this->escape($this->truncateString($body ?? '', (int) config('activity-log.max_body_bytes', 2000))) . "'";
            }
        }

        $command = implode(" \\\n  ", $parts);

        return $this->truncateString($command, (int) config('activity-log.max_curl_length', 4000));
    }

    protected function truncateString(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        return strlen($value) <= $limit ? $value : substr($value, 0, $limit) . '...';
    }

    protected function escape(string $value): string
    {
        return str_replace("'", "'\"'\"'", $value);
    }
}
