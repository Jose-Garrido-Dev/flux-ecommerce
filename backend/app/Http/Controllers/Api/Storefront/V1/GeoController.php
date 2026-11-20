<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Shipping\Models\Region;
use Illuminate\Http\JsonResponse;

/** Regiones y, si ya se cargaron desde el listado oficial, sus comunas. */
class GeoController extends StorefrontController
{
    public function regions(): JsonResponse
    {
        return response()->json(['data' => Region::query()->where('is_enabled', true)->orderBy('position')
            ->with(['communes' => fn ($q) => $q->where('is_enabled', true)->orderBy('name')])->get()
            ->map(fn (Region $r) => [
                'code' => $r->code,
                'name' => $r->name,
                'communes' => $r->communes->pluck('name')->all(),
            ])->all()]);
    }
}
