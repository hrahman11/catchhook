<?php

use App\Events\RequestCaptured;
use App\Models\Bin;
use App\Models\CapturedRequest;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('broadcasts every captured request to the bin channel', function () {
    Event::fake([RequestCaptured::class]);
    $bin = Bin::factory()->create();

    $this->postJson("/h/{$bin->id}/orders?id=7", ['status' => 'paid'])->assertOk();

    $captured = CapturedRequest::sole();

    Event::assertDispatched(RequestCaptured::class, function (RequestCaptured $event) use ($bin, $captured) {
        $channels = $event->broadcastOn();

        return count($channels) === 1
            && $channels[0] instanceof Channel
            && $channels[0]->name === "bins.{$bin->id}"
            && $event->broadcastAs() === 'request.captured'
            && $event->broadcastWith()['request']['id'] === $captured->id;
    });
});

it('only broadcasts a lightweight summary', function () {
    $request = CapturedRequest::factory()->create([
        'method' => 'PATCH',
        'path' => '/users/1',
        'query_string' => 'notify=1',
    ]);

    $payload = (new RequestCaptured($request))->broadcastWith()['request'];

    expect($payload)
        ->toHaveKeys(['id', 'method', 'path', 'query_string', 'content_type', 'size', 'ip', 'created_at'])
        ->not->toHaveKeys(['headers', 'body'])
        ->method->toBe('PATCH')
        ->path->toBe('/users/1')
        ->query_string->toBe('notify=1');
});

it('queues the broadcast instead of blocking the sender', function () {
    Queue::fake();
    $bin = Bin::factory()->create();

    $this->postJson("/h/{$bin->id}")->assertOk();

    Queue::assertPushed(BroadcastEvent::class, fn (BroadcastEvent $job) => $job->event instanceof RequestCaptured);
});

it('does not broadcast rejected requests', function () {
    Event::fake([RequestCaptured::class]);
    $expired = Bin::factory()->expired()->create();

    $this->postJson("/h/{$expired->id}")->assertStatus(410);
    $this->postJson('/h/aaaaaaaaaaaaaaaa')->assertNotFound();

    Event::assertNotDispatched(RequestCaptured::class);
});
