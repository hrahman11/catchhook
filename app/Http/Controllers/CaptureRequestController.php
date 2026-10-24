<?php

namespace App\Http\Controllers;

use App\Actions\CaptureIncomingRequest;
use App\Models\Bin;
use App\Models\CapturedRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Sleep;
use Symfony\Component\HttpFoundation\Response;

class CaptureRequestController extends Controller
{
    public function __invoke(Request $request, CaptureIncomingRequest $capture, Bin $bin, string $path = ''): Response
    {
        abort_if($bin->isExpired(), 410, 'This bin has expired.');

        $captured = $capture->handle($bin, $request, $path);

        return $bin->hasCustomResponse()
            ? $this->customResponse($bin)
            : $this->defaultResponse($captured);
    }

    private function defaultResponse(CapturedRequest $captured): Response
    {
        return response()->json([
            'message' => 'Request captured.',
            'request_id' => $captured->id,
        ]);
    }

    private function customResponse(Bin $bin): Response
    {
        // The request is already stored and broadcast, so the delay only affects the sender.
        if ($bin->response_delay_ms > 0) {
            Sleep::for(min($bin->response_delay_ms, config('catchhook.response.max_delay_ms')))->milliseconds();
        }

        $response = response($bin->response_body ?? '', $bin->response_status);

        foreach ($bin->response_headers ?? [] as ['name' => $name, 'value' => $value]) {
            $response->headers->set($name, $value, replace: false);
        }

        if (! $response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        }

        return $response;
    }
}
