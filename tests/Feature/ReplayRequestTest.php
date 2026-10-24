<?php

use App\Models\CapturedRequest;
use App\Services\Replay\HostResolver;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // Never touch real DNS in tests: map hostnames to fixed addresses.
    $this->app->instance(HostResolver::class, new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return match ($host) {
                'hooks.example.com' => ['93.184.216.34'],
                'localhost' => ['127.0.0.1'],
                default => filter_var($host, FILTER_VALIDATE_IP) ? [$host] : [],
            };
        }
    });

    $this->captured = CapturedRequest::factory()->create([
        'method' => 'POST',
        'headers' => [
            'host' => ['catchhook.test'],
            'content-type' => ['application/json'],
            'content-length' => ['17'],
            'x-github-event' => ['push'],
            'x-hub-signature-256' => ['sha256=abc'],
        ],
    ]);
    $this->captured->setRawBody('{"ref":"main"}')->save();
});

function replay(array $data = []): TestResponse
{
    return test()->postJson('/requests/'.test()->captured->id.'/replay', [
        'target_url' => 'https://hooks.example.com/github',
        ...$data,
    ]);
}

it('replays the original request to the target', function () {
    Http::fake(['hooks.example.com/*' => Http::response(['received' => true], 202, ['X-Trace' => 'abc'])]);

    replay()
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'status' => 202,
            'reason' => 'Accepted',
            'body' => '{"received":true}',
            'is_binary' => false,
            'truncated' => false,
            'error' => null,
        ])
        ->assertJsonPath('headers.X-Trace', ['abc']);

    Http::assertSent(fn (ClientRequest $request) => $request->method() === 'POST'
        && $request->url() === 'https://hooks.example.com/github'
        && $request->body() === '{"ref":"main"}'
        && $request->header('X-GitHub-Event') === ['push']
        && $request->header('X-Hub-Signature-256') === ['sha256=abc']
        && $request->header('X-Catchhook-Replay') === [$this->captured->id]
        && $request->header('Host') !== ['catchhook.test']
        && ! $request->hasHeader('Content-Length', '17'));
});

it('applies edits to the method, headers and body', function () {
    Http::fake();

    replay([
        'method' => 'PUT',
        'headers' => [
            ['name' => 'Content-Type', 'value' => 'text/plain'],
            ['name' => 'X-Custom', 'value' => 'one'],
            ['name' => 'X-Custom', 'value' => 'two'],
        ],
        'body' => '  edited body with spaces  ',
    ])->assertOk();

    Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PUT'
        && $request->body() === '  edited body with spaces  '
        && $request->header('Content-Type') === ['text/plain']
        && $request->header('X-Custom') === ['one', 'two']
        && ! $request->hasHeader('X-GitHub-Event'));
});

it('replays binary bodies byte-for-byte', function () {
    Http::fake();
    $bytes = "\x89PNG\r\n\x1a\n\0\xFF";
    $this->captured->setRawBody($bytes)->save();

    replay()->assertOk();

    Http::assertSent(fn (ClientRequest $request) => $request->body() === $bytes);
});

it('blocks replays to internal addresses', function (string $url) {
    Http::fake();

    replay(['target_url' => $url])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('target_url');

    Http::assertNothingSent();
})->with([
    'http://localhost:3000/webhooks',
    'http://127.0.0.1/',
    'http://169.254.169.254/latest/meta-data/',
    'http://10.0.0.8/',
]);

it('allows internal addresses on self-hosted instances that opt in', function () {
    config(['catchhook.replay.allow_private_targets' => true]);
    Http::fake();

    replay(['target_url' => 'http://localhost:3000/webhooks'])->assertOk();

    Http::assertSentCount(1);
});

it('does not follow redirects', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);

    replay()->assertOk()->assertJson(['status' => 302]);

    Http::assertSentCount(1);
});

it('reports connection failures without erroring', function () {
    Http::fake(['*' => Http::failedConnection('Connection refused')]);

    replay()
        ->assertOk()
        ->assertJson(['ok' => false, 'status' => null])
        ->assertJsonPath('error', fn (string $error) => str_contains($error, 'Connection refused'));
});

it('truncates large responses', function () {
    config(['catchhook.replay.max_response_bytes' => 10]);
    Http::fake(['*' => Http::response(str_repeat('a', 25))]);

    replay()->assertOk()->assertJson([
        'body' => str_repeat('a', 10),
        'size' => 25,
        'truncated' => true,
    ]);
});

it('validates the replay input', function (array $data, string $field) {
    Http::fake();

    replay($data)->assertJsonValidationErrorFor($field);

    Http::assertNothingSent();
})->with([
    'missing url' => [['target_url' => ''], 'target_url'],
    'non-http scheme' => [['target_url' => 'ftp://hooks.example.com/'], 'target_url'],
    'unknown method' => [['method' => 'TRACE'], 'method'],
    'invalid header name' => [['headers' => [['name' => "X-Bad\r\nInjected", 'value' => '1']]], 'headers.0.name'],
    'header value injection' => [['headers' => [['name' => 'X-Ok', 'value' => "a\r\nX-Evil: 1"]]], 'headers.0.value'],
]);

it('returns 404 for unknown requests', function () {
    $this->postJson('/requests/01aaaaaaaaaaaaaaaaaaaaaaaa/replay', ['target_url' => 'https://hooks.example.com/'])
        ->assertNotFound();
});
