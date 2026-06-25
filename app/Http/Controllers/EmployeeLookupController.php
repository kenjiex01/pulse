<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeLookupController extends Controller
{
    public function provinces(Request $request): JsonResponse
    {
        $regionId = $request->integer('region_id');

        if (! $regionId) {
            return response()->json([]);
        }

        $provinces = Province::query()
            ->active()
            ->where('region_id', $regionId)
            ->orderBy('province_name')
            ->get(['province_id', 'province_name']);

        return response()->json($provinces);
    }

    public function cities(Request $request): JsonResponse
    {
        $provinceId = $request->integer('province_id');

        if (! $provinceId) {
            return response()->json([]);
        }

        $cities = City::query()
            ->active()
            ->where('province_id', $provinceId)
            ->orderBy('city_name')
            ->get(['city_id', 'city_name', 'type', 'postal_code']);

        return response()->json($cities);
    }
}
