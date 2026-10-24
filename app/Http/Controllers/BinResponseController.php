<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBinResponseRequest;
use App\Models\Bin;
use Illuminate\Http\RedirectResponse;

class BinResponseController extends Controller
{
    public function update(UpdateBinResponseRequest $request, Bin $bin): RedirectResponse
    {
        $bin->update($request->toAttributes());

        return back();
    }

    public function destroy(Bin $bin): RedirectResponse
    {
        $bin->resetResponse();

        return back();
    }
}
