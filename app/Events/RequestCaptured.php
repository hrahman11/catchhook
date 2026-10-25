<?php

namespace App\Events;

use App\Http\Resources\CapturedRequestSummaryResource;
use App\Models\CapturedRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushes a newly captured request to everyone watching the bin.
 *
 * Broadcast through the queue so the sender's response is never delayed by
 * the WebSocket server. Only the summary is sent; the dashboard fetches the
 * full headers and body on demand, which keeps large payloads off the socket.
 */
class RequestCaptured implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public string $binId;

    /** @var array<array-key, mixed> */
    public array $request;

    public function __construct(CapturedRequest $request)
    {
        // Serialize the payload now so the queued broadcast never re-queries
        // the database (and still works if the request is deleted meanwhile).
        $this->binId = $request->bin_id;
        $this->request = (new CapturedRequestSummaryResource($request))->resolve();
    }

    public static function channelName(string $binId): string
    {
        return "bins.{$binId}";
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel(self::channelName($this->binId))];
    }

    public function broadcastAs(): string
    {
        return 'request.captured';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['request' => $this->request];
    }
}
