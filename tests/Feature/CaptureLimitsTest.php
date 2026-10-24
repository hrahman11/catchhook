<?php

use App\Models\Bin;
use App\Models\CapturedRequest;
use Illuminate\Console\Scheduling\Schedule;

describe('rate limiting', function () {
    it('limits how fast a single bin can receive requests', function () {
        config(['catchhook.limits.captures_per_minute_per_bin' => 3]);
        [$busy, $quiet] = Bin::factory()->count(2)->create();

        foreach (range(1, 3) as $attempt) {
            $this->post("/h/{$busy->id}")->assertOk();
        }

        $this->post("/h/{$busy->id}")
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertHeader('Content-Security-Policy', 'sandbox');

        $this->post("/h/{$quiet->id}")->assertOk();

        expect($busy->requests()->count())->toBe(3);
    });

    it('limits how fast a single client can send requests across bins', function () {
        config(['catchhook.limits.captures_per_minute_per_ip' => 3]);
        $bins = Bin::factory()->count(4)->create();

        foreach ($bins->take(3) as $bin) {
            $this->post("/h/{$bin->id}")->assertOk();
        }

        $this->post("/h/{$bins->last()->id}")->assertTooManyRequests();

        expect(CapturedRequest::count())->toBe(3);
    });

    it('counts requests to unknown bins, so IDs cannot be brute-forced freely', function () {
        config(['catchhook.limits.captures_per_minute_per_ip' => 2]);

        $this->post('/h/aaaaaaaaaaaaaaaa')->assertNotFound();
        $this->post('/h/bbbbbbbbbbbbbbbb')->assertNotFound();
        $this->post('/h/cccccccccccccccc')->assertTooManyRequests();
    });
});

describe('body size limit', function () {
    beforeEach(fn () => config(['catchhook.limits.max_body_kb' => 1]));

    it('accepts bodies up to the limit', function () {
        $bin = Bin::factory()->create();

        $this->call('POST', "/h/{$bin->id}", content: str_repeat('a', 1024))->assertOk();

        expect(CapturedRequest::sole()->size)->toBe(1024);
    });

    it('rejects larger bodies without storing them', function () {
        $bin = Bin::factory()->create();

        $this->call('POST', "/h/{$bin->id}", content: str_repeat('a', 1025))
            ->assertStatus(413)
            ->assertJson(['message' => 'Request bodies are limited to 1 KB.']);

        expect(CapturedRequest::count())->toBe(0);
    });

    it('rejects oversized uploads based on the declared length', function () {
        $bin = Bin::factory()->create();

        $this->call('POST', "/h/{$bin->id}", server: ['CONTENT_LENGTH' => 10 * 1024 * 1024], content: 'small')
            ->assertStatus(413);
    });
});

describe('storage', function () {
    it('keeps only the most recent requests per bin', function () {
        config(['catchhook.limits.max_requests_per_bin' => 3]);
        $bin = Bin::factory()->create();
        $other = CapturedRequest::factory()->create();

        foreach (range(1, 5) as $n) {
            $this->post("/h/{$bin->id}/{$n}")->assertOk();
        }

        expect($bin->requests()->newestFirst()->pluck('path')->all())->toBe(['/5', '/4', '/3'])
            ->and($other->fresh())->not->toBeNull();
    });

    it('prunes expired bins and their requests', function () {
        $expired = Bin::factory()->expired()->create();
        CapturedRequest::factory()->for($expired)->count(2)->create();
        $active = Bin::factory()->create();
        CapturedRequest::factory()->for($active)->create();

        $this->artisan('model:prune', ['--model' => [Bin::class]])->assertSuccessful();

        expect(Bin::pluck('id')->all())->toBe([$active->id])
            ->and(CapturedRequest::where('bin_id', $expired->id)->count())->toBe(0)
            ->and($active->requests()->count())->toBe(1);
    });

    it('schedules pruning every hour', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'model:prune'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 * * * *');
    });
});
