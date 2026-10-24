<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplayRequest;
use App\Models\CapturedRequest;
use App\Services\Replay\Replayer;
use App\Services\Replay\UnsafeTargetException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReplayCapturedRequestController extends Controller
{
    public function __invoke(ReplayRequest $request, CapturedRequest $capturedRequest, Replayer $replayer): JsonResponse
    {
        try {
            $result = $replayer->replay(
                $capturedRequest,
                url: $request->validated('target_url'),
                method: $request->validated('method'),
                headers: $request->replayHeaders(),
                body: $request->replayBody(),
            );
        } catch (UnsafeTargetException $e) {
            throw ValidationException::withMessages(['target_url' => $e->getMessage()]);
        }

        return response()->json($result);
    }
}
