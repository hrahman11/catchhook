<?php

namespace App\Http\Controllers;

use App\Actions\CaptureIncomingRequest;
use App\Models\Bin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaptureRequestController extends Controller
{
    public function __invoke(Request $request, CaptureIncomingRequest $capture, Bin $bin, string $path = ''): JsonResponse
    {
        abort_if($bin->isExpired(), 410, 'This bin has expired.');

        $captured = $capture->handle($bin, $request, $path);

        return response()->json([
            'message' => 'Request captured.',
            'request_id' => $captured->id,
        ]);
    }
}
