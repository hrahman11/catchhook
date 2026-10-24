<?php

namespace App\Http\Controllers;

use App\Http\Resources\BinResource;
use App\Http\Resources\CapturedRequestResource;
use App\Http\Resources\CapturedRequestSummaryResource;
use App\Models\Bin;
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
        // Props are closures so partial reloads (e.g. selecting a request) only run the queries they need.
        return Inertia::render('Bins/Show', [
            'bin' => fn () => new BinResource($bin),
            'requests' => fn () => CapturedRequestSummaryResource::collection(
                $bin->requests()
                    ->select(['id', 'bin_id', 'method', 'path', 'query_string', 'content_type', 'size', 'ip', 'created_at'])
                    ->newestFirst()
                    ->limit(self::LIST_LIMIT)
                    ->get()
            ),
            'total' => fn () => $bin->requests()->count(),
            'selected' => function () use ($request, $bin) {
                $selectedId = $request->query('r');

                $selected = is_string($selectedId)
                    ? $bin->requests()->find($selectedId)
                    : $bin->requests()->newestFirst()->first();

                return $selected ? new CapturedRequestResource($selected) : null;
            },
        ]);
    }
}
