<?php

use App\Models\Bin;
use App\Models\CapturedRequest;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->bin = Bin::factory()->create();

    $this->stripe = tap(CapturedRequest::factory()->for($this->bin)->make([
        'method' => 'POST',
        'path' => '/stripe/events',
        'headers' => ['stripe-signature' => ['t=1,v1=abc']],
    ]))->setRawBody('{"type":"invoice.payment_failed"}');
    $this->stripe->save();

    $this->github = tap(CapturedRequest::factory()->for($this->bin)->make([
        'method' => 'POST',
        'path' => '/github',
        'query_string' => 'delivery=Push-42',
        'headers' => ['x-github-event' => ['push']],
    ]))->setRawBody('{"ref":"refs/heads/main"}');
    $this->github->save();

    $this->health = tap(CapturedRequest::factory()->for($this->bin)->make([
        'method' => 'GET',
        'path' => '/health',
        'headers' => ['user-agent' => ['uptime-bot']],
    ]))->setRawBody('');
    $this->health->save();
});

function searchIds(array $query): array
{
    $ids = [];

    test()->get('/bins/'.test()->bin->id.'?'.http_build_query($query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$ids) {
            $ids = collect($page->toArray()['props']['requests'])->pluck('id')->sort()->values()->all();
        });

    return $ids;
}

it('finds requests by path, query string, header or body', function (string $term, string $expected) {
    expect(searchIds(['q' => $term]))->toBe([$this->{$expected}->id]);
})->with([
    'path' => ['stripe/events', 'stripe'],
    'query string, case-insensitively' => ['push-42', 'github'],
    'header name' => ['x-github-event', 'github'],
    'header value' => ['uptime-bot', 'health'],
    'body' => ['payment_failed', 'stripe'],
]);

it('filters by method', function () {
    expect(searchIds(['method' => 'GET']))->toBe([$this->health->id]);
});

it('combines search and method filters', function () {
    expect(searchIds(['q' => 'refs/heads', 'method' => 'POST']))->toBe([$this->github->id])
        ->and(searchIds(['q' => 'refs/heads', 'method' => 'GET']))->toBe([]);
});

it('treats LIKE wildcards in the search term literally', function () {
    expect(searchIds(['q' => '%']))->toBe([])
        ->and(searchIds(['q' => '_']))->toBe([$this->stripe->id]); // only "payment_failed" contains an underscore
});

it('does not search inside binary bodies', function () {
    $binary = CapturedRequest::factory()->for($this->bin)->make()->setRawBody("\xFF\xFEpayment_failed\0");
    $binary->save();

    expect(searchIds(['q' => 'payment_failed']))->toBe([$this->stripe->id]);
});

it('reports how many requests match alongside the total', function () {
    $this->get("/bins/{$this->bin->id}?q=github&method=POST")
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 3)
            ->where('matches', 1)
            ->where('filters.q', 'github')
            ->where('filters.method', 'POST'));

    $this->get("/bins/{$this->bin->id}")
        ->assertInertia(fn (Assert $page) => $page->where('matches', null)->where('filters.q', ''));
});

it('never searches other bins', function () {
    CapturedRequest::factory()->create(['path' => '/stripe/events']);

    expect(searchIds(['q' => 'stripe']))->toBe([$this->stripe->id]);
});

it('loads a second request for comparison', function () {
    $this->get("/bins/{$this->bin->id}?r={$this->stripe->id}&c={$this->github->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $this->stripe->id)
            ->where('compare.id', $this->github->id)
            ->has('compare.headers')
            ->has('compare.body'));
});

it('does not compare against requests from another bin', function () {
    $foreign = CapturedRequest::factory()->create();

    $this->get("/bins/{$this->bin->id}?c={$foreign->id}")
        ->assertInertia(fn (Assert $page) => $page->where('compare', null));
});
