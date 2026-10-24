<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects capture requests whose body exceeds the configured limit.
 */
class LimitHookBodySize
{
    public function handle(Request $request, Closure $next): Response
    {
        $max = config('catchhook.limits.max_body_kb') * 1024;

        // Check the declared length first so oversized uploads are refused
        // without reading them, then the actual body for chunked requests.
        $declared = (int) $request->server('CONTENT_LENGTH', 0);

        if ($declared > $max || strlen($request->getContent()) > $max) {
            abort(413, sprintf('Request bodies are limited to %d KB.', $max / 1024));
        }

        return $next($request);
    }
}
