<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplayRequest;
use App\Http\Resources\BinResource;
use App\Http\Resources\CapturedRequestResource;
use App\Http\Resources\CapturedRequestSummaryResource;
use App\Models\Bin;
use App\Models\CapturedRequest;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BinController extends Controller
{
    /**
     * Maximum number of requests listed in the dashboard sidebar.
     */
    public const LIST_LIMIT = 100;

    public function store(): RedirectResponse
    {
        $bin = Bin::create();

        return to_route('bins.show', $bin);
    }

    public function show(Request $request, Bin $bin): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'method' => ['nullable', 'string', 'in:'.implode(',', ReplayRequest::METHODS)],
        ]);

        $filtered = fn (): HasMany => $bin->requests()
            ->search($filters['q'] ?? null)
            ->withMethod($filters['method'] ?? null);

        // Props are closures so partial reloads (e.g. selecting a request) only run the queries they need.
        return Inertia::render('Bins/Show', [
            'bin' => fn () => new BinResource($bin),
            'filters' => fn () => [
                'q' => $filters['q'] ?? '',
                'method' => $filters['method'] ?? null,
            ],
            'requests' => fn () => CapturedRequestSummaryResource::collection(
                $filtered()
                    ->select(['id', 'bin_id', 'method', 'path', 'query_string', 'content_type', 'size', 'ip', 'created_at'])
                    ->newestFirst()
                    ->limit(self::LIST_LIMIT)
                    ->get()
            ),
            'total' => fn () => $bin->requests()->count(),
            'matches' => fn () => array_filter($filters) ? $filtered()->count() : null,
            // Without an explicit selection, open the newest request.
            'selected' => fn () => $this->resource($request->has('r')
                ? $this->findRequest($bin, $request->query('r'))
                : $bin->requests()->newestFirst()->first()),
            'compare' => fn () => $this->resource($this->findRequest($bin, $request->query('c'))),
        ]);
    }

    /**
     * Find a request by ID, scoped to the bin so other bins' requests are never exposed.
     */
    private function findRequest(Bin $bin, mixed $id): ?CapturedRequest
    {
        return is_string($id) ? $bin->requests()->find($id) : null;
    }

    private function resource(?CapturedRequest $request): ?CapturedRequestResource
    {
        return $request ? new CapturedRequestResource($request) : null;
    }
}
