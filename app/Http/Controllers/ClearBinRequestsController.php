<?php

namespace App\Http\Controllers;

use App\Models\Bin;
use Illuminate\Http\RedirectResponse;

class ClearBinRequestsController extends Controller
{
    public function __invoke(Bin $bin): RedirectResponse
    {
        $bin->requests()->delete();

        return to_route('bins.show', $bin);
    }
}
