<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\AssetCode;

class AssetController extends Controller
{
    /**
     * LIST ASSETS (WITH UNIQUE CODES)
     */
    public function index(Request $request)
    {
        $query = Asset::with([
            'company',
            'category',
            'assetCode' // REQUIRED
        ]);

        if ($request->has('has_unique_code')) {
            $query->whereHas('assetCode');
        }

        return response()->json($query->get());
    }

    /**
     * SEARCH BY UNIQUE CODE
     */
    public function getAssetByUniqueCode(Request $request)
    {
        $request->validate([
            'unique_code' => 'required|string',
        ]);

        $assetCode = AssetCode::with([
            'asset.company',
            'asset.category',
            'asset.assetCode'
        ])->where('unique_code', $request->unique_code)->first();

        if (!$assetCode || !$assetCode->asset) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json([
            'unique_code' => $assetCode->unique_code,
            'asset' => $assetCode->asset
        ]);
    }

    /**
     * UNIQUE CODE SUGGESTIONS
     */
    public function suggestUniqueCodes(Request $request)
    {
        $q = $request->query('q', '');

        return response()->json(
            AssetCode::where('unique_code', 'like', "%{$q}%")
                ->limit(10)
                ->pluck('unique_code')
        );
    }
}
