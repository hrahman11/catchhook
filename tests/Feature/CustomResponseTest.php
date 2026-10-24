<?php

use App\Models\Bin;
use App\Models\CapturedRequest;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

function binWithResponse(array $attributes = []): Bin
{
    return Bin::factory()->create([
        'response_status' => 201,
        'response_headers' => [['name' => 'x-request-source', 'value' => 'catchhook']],
        'response_body' => '{"accepted":true}',
        'response_delay_ms' => 0,
        ...$attributes,
    ]);
}

it('answers with the default acknowledgement when no response is configured', function () {
    $bin = Bin::factory()->create();

    $this->post("/h/{$bin->id}")
        ->assertOk()
        ->assertJsonStructure(['message', 'request_id']);
});

it('answers with the configured status, headers and body', function () {
    $bin = binWithResponse(['response_headers' => [
        ['name' => 'content-type', 'value' => 'application/json'],
        ['name' => 'x-request-source', 'value' => 'catchhook'],
    ]]);

    $response = $this->post("/h/{$bin->id}");

    $response->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('X-Request-Source', 'catchhook');

    expect($response->getContent())->toBe('{"accepted":true}')
        ->and(CapturedRequest::count())->toBe(1);
});

it('defaults custom bodies to plain text', function () {
    $this->post('/h/'.binWithResponse()->id)->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});

it('waits for the configured delay before responding', function () {
    Sleep::fake();

    $this->post('/h/'.binWithResponse(['response_delay_ms' => 1500])->id)->assertCreated();

    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds === 1500.0);
});

it('caps the delay', function () {
    Sleep::fake();
    config(['catchhook.response.max_delay_ms' => 2000]);

    $this->post('/h/'.binWithResponse(['response_delay_ms' => 9000])->id);

    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds === 2000.0);
});

it('sandboxes every capture response so custom bodies cannot script the app origin', function (bool $custom) {
    $bin = $custom
        ? binWithResponse(['response_headers' => [['name' => 'content-type', 'value' => 'text/html']], 'response_body' => '<script>alert(1)</script>'])
        : Bin::factory()->create();

    $this->post("/h/{$bin->id}")
        ->assertHeader('Content-Security-Policy', 'sandbox')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'no-referrer');
})->with(['default response' => false, 'custom html response' => true]);

it('sandboxes error responses from capture URLs too', function () {
    $this->post('/h/aaaaaaaaaaaaaaaa')
        ->assertNotFound()
        ->assertHeader('Content-Security-Policy', 'sandbox');
});

it('saves a custom response', function () {
    $bin = Bin::factory()->create();

    $this->from("/bins/{$bin->id}")->put("/bins/{$bin->id}/response", [
        'status' => 503,
        'headers' => [['name' => 'Retry-After', 'value' => '30']],
        'body' => "  Service unavailable\n",
        'delay_ms' => 250,
    ])->assertRedirect("/bins/{$bin->id}");

    expect($bin->fresh())
        ->response_status->toBe(503)
        ->response_headers->toBe([['name' => 'retry-after', 'value' => '30']])
        ->response_body->toBe("  Service unavailable\n")
        ->response_delay_ms->toBe(250);
});

it('exposes the response settings on the dashboard', function () {
    $bin = binWithResponse(['response_delay_ms' => 100]);

    $this->get("/bins/{$bin->id}")->assertInertia(fn (Assert $page) => $page
        ->where('bin.response.status', 201)
        ->where('bin.response.body', '{"accepted":true}')
        ->where('bin.response.delay_ms', 100)
        ->where('bin.response.headers.0.name', 'x-request-source')
    );

    $this->get('/bins/'.Bin::factory()->create()->id)->assertInertia(fn (Assert $page) => $page->where('bin.response', null));
});

it('resets to the default response', function () {
    $bin = binWithResponse(['response_delay_ms' => 500]);

    $this->delete("/bins/{$bin->id}/response")->assertRedirect();

    expect($bin->fresh())
        ->hasCustomResponse()->toBeFalse()
        ->response_headers->toBeNull()
        ->response_delay_ms->toBe(0);

    $this->post("/h/{$bin->id}")->assertOk()->assertJsonStructure(['request_id']);
});

it('rejects reserved or unsafe response settings', function (array $data, string $field) {
    $bin = Bin::factory()->create();

    $this->put("/bins/{$bin->id}/response", [
        'status' => 200,
        'headers' => [],
        'body' => null,
        'delay_ms' => 0,
        ...$data,
    ])->assertSessionHasErrors($field);

    expect($bin->fresh()->hasCustomResponse())->toBeFalse();
})->with([
    'informational status' => [['status' => 101], 'status'],
    'invalid status' => [['status' => 700], 'status'],
    'set-cookie' => [['headers' => [['name' => 'Set-Cookie', 'value' => 'session=stolen']]], 'headers.0.name'],
    'csp override' => [['headers' => [['name' => 'Content-Security-Policy', 'value' => 'default-src *']]], 'headers.0.name'],
    'header injection' => [['headers' => [['name' => 'X-Ok', 'value' => "1\r\nSet-Cookie: a=b"]]], 'headers.0.value'],
    'delay too long' => [['delay_ms' => 60_000], 'delay_ms'],
]);
