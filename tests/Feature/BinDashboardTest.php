<?php

use App\Models\Bin;
use App\Models\CapturedRequest;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the landing page', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Home'));
});

it('creates a bin and redirects to its dashboard', function () {
    $response = $this->post('/bins');

    $bin = Bin::sole();

    $response->assertRedirect("/bins/{$bin->id}");
    expect($bin->expires_at->isFuture())->toBeTrue();
});

it('rate limits bin creation per IP', function () {
    foreach (range(1, 10) as $attempt) {
        $this->post('/bins')->assertRedirect();
    }

    $this->post('/bins')->assertTooManyRequests();

    expect(Bin::count())->toBe(10);
});

it('shows the bin with its requests newest first', function () {
    $bin = Bin::factory()->create();
    $older = CapturedRequest::factory()->for($bin)->create(['created_at' => now()->subMinute()]);
    $newer = CapturedRequest::factory()->for($bin)->create(['method' => 'PUT']);
    CapturedRequest::factory()->create(); // belongs to another bin

    $this->get("/bins/{$bin->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Bins/Show')
            ->where('bin.id', $bin->id)
            ->where('bin.url', url("/h/{$bin->id}"))
            ->where('bin.expired', false)
            ->where('total', 2)
            ->has('requests', 2)
            ->where('requests.0.id', $newer->id)
            ->where('requests.1.id', $older->id)
            ->missing('requests.0.body')
            ->where('selected.id', $newer->id)
            ->where('selected.method', 'PUT')
            ->has('selected.headers')
            ->has('selected.body')
        );
});

it('selects a specific request with a partial reload', function () {
    $bin = Bin::factory()->create();
    $first = CapturedRequest::factory()->for($bin)->create(['created_at' => now()->subMinute()]);
    CapturedRequest::factory()->for($bin)->create();

    $this->get("/bins/{$bin->id}?r={$first->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $first->id)
            ->reloadOnly('selected', fn (Assert $reload) => $reload
                ->where('selected.id', $first->id)
                ->missing('requests')
            )
        );
});

it('does not expose requests from another bin', function () {
    $bin = Bin::factory()->create();
    $foreign = CapturedRequest::factory()->create();

    $this->get("/bins/{$bin->id}?r={$foreign->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});

it('returns base64 bodies for binary requests', function () {
    $bin = Bin::factory()->create();
    $bytes = "\x89PNG\r\n\x1a\n\0\0";
    CapturedRequest::factory()->for($bin)->make()->setRawBody($bytes)->save();

    $this->get("/bins/{$bin->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.is_binary', true)
            ->where('selected.body', base64_encode($bytes))
        );
});

it('clears every request in a bin', function () {
    $bin = Bin::factory()->create();
    CapturedRequest::factory()->for($bin)->count(3)->create();
    $other = CapturedRequest::factory()->create();

    $this->delete("/bins/{$bin->id}/requests")->assertRedirect("/bins/{$bin->id}");

    expect($bin->requests()->count())->toBe(0)
        ->and($other->fresh())->not->toBeNull();
});

it('returns 404 for unknown bins', function () {
    $this->get('/bins/aaaaaaaaaaaaaaaa')->assertNotFound();
});
