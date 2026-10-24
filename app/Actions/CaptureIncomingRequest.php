<?php

namespace App\Actions;

use App\Models\Bin;
use App\Models\CapturedRequest;
use Illuminate\Http\Request;

class CaptureIncomingRequest
{
    /**
     * Persist an incoming HTTP request exactly as it was received.
     */
    public function handle(Bin $bin, Request $request, string $path = ''): CapturedRequest
    {
        $captured = new CapturedRequest([
            'method' => $request->getMethod(),
            'path' => '/'.ltrim($path, '/'),
            // Read the raw query string so parameter order and duplicates are preserved.
            'query_string' => $request->server->get('QUERY_STRING') ?: null,
            'headers' => $request->headers->all(),
            'content_type' => $request->headers->get('Content-Type'),
            'ip' => $request->ip(),
        ]);

        // getContent() returns the untouched body, unaffected by input middleware.
        $captured->setRawBody($request->getContent());

        $bin->requests()->save($captured);

        return $captured;
    }
}
