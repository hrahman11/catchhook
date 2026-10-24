<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardens responses served from capture URLs.
 *
 * Bins can return user-defined bodies and headers from the application's own
 * origin. The sandbox CSP gives any rendered page an opaque origin, so HTML or
 * JavaScript in a custom response can't read the dashboard's cookies or DOM,
 * and cookies are never set from a capture URL.
 */
class SecureHookResponse
{
    /**
     * Response headers a bin is never allowed to define.
     */
    public const RESERVED_HEADERS = [
        'set-cookie',
        'content-length',
        'content-encoding',
        'transfer-encoding',
        'connection',
        'content-security-policy',
        'x-content-type-options',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->remove('Set-Cookie');
        $response->headers->set('Content-Security-Policy', 'sandbox');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
