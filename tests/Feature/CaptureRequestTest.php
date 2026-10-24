<?php

use App\Models\Bin;
use App\Models\CapturedRequest;

it('captures a JSON webhook with its headers and body', function () {
    $bin = Bin::factory()->create();
    $payload = '{"type":"invoice.paid","data":{"amount":4200}}';

    $response = $this->call('POST', "/h/{$bin->id}", server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => 'sha256=abc123',
    ], content: $payload);

    $response->assertOk()->assertJsonStructure(['message', 'request_id']);

    $captured = CapturedRequest::sole();

    expect($captured)
        ->id->toBe($response->json('request_id'))
        ->bin_id->toBe($bin->id)
        ->method->toBe('POST')
        ->path->toBe('/')
        ->content_type->toBe('application/json')
        ->size->toBe(strlen($payload))
        ->and($captured->rawBody())->toBe($payload)
        ->and($captured->headers['x-signature'])->toBe(['sha256=abc123']);
});

it('accepts any HTTP method', function (string $method) {
    $bin = Bin::factory()->create();

    $this->call($method, "/h/{$bin->id}")->assertOk();

    expect(CapturedRequest::sole()->method)->toBe($method);
})->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);

it('keeps the sub-path and query string', function () {
    $bin = Bin::factory()->create();

    $this->post("/h/{$bin->id}/stripe/events?source=cli&attempt=2")->assertOk();

    expect(CapturedRequest::sole())
        ->path->toBe('/stripe/events')
        ->query_string->toBe('source=cli&attempt=2');
});

it('preserves the raw body byte-for-byte', function () {
    $bin = Bin::factory()->create();
    $body = "  padded form=value&empty=  \n";

    $this->call('POST', "/h/{$bin->id}", server: [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
    ], content: $body)->assertOk();

    expect(CapturedRequest::sole()->rawBody())->toBe($body);
});

it('stores binary payloads losslessly', function () {
    $bin = Bin::factory()->create();
    $bytes = random_bytes(256)."\0\xFF\xFE";

    $this->call('POST', "/h/{$bin->id}", server: [
        'CONTENT_TYPE' => 'application/octet-stream',
    ], content: $bytes)->assertOk();

    $captured = CapturedRequest::sole();

    expect($captured->isBinary())->toBeTrue()
        ->and($captured->rawBody())->toBe($bytes)
        ->and($captured->size)->toBe(strlen($bytes));
});

it('does not start a session or require a CSRF token', function () {
    $bin = Bin::factory()->create();

    $response = $this->post("/h/{$bin->id}", ['hello' => 'world']);

    $response->assertOk();
    expect($response->headers->getCookies())->toBeEmpty();
});

it('returns 404 for unknown or malformed bins', function (string $binId) {
    $this->postJson("/h/{$binId}")->assertNotFound();

    expect(CapturedRequest::count())->toBe(0);
})->with([
    'unknown' => 'aaaaaaaaaaaaaaaa',
    'malformed' => 'not-a-valid-bin!',
]);

it('rejects requests to expired bins', function () {
    $bin = Bin::factory()->expired()->create();

    $this->postJson("/h/{$bin->id}")
        ->assertStatus(410)
        ->assertJson(['message' => 'This bin has expired.']);

    expect(CapturedRequest::count())->toBe(0);
});
