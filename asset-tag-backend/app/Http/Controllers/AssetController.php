<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\AssetCode;
use App\Models\Category;
use App\Models\Companies; // Ensure this matches your model name (Companies vs Company)
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AssetController extends Controller
{
    /**
     * LIST ASSETS (WITH UNIQUE CODES & RELATIONSHIPS)
     * Used for the main list and the Excel Export
     */
    public function index(Request $request)
    {
        $query = Asset::with([
            'company',
            'category',
            'assetCode' 
        ]);

        // Filter: only assets that have a unique code assigned
        if ($request->has('has_unique_code')) {
            $query->whereHas('assetCode');
        }

        return response()->json($query->get());
    }

    /**
     * DASHBOARD SUMMARY
     * Fixes the "undefined method summary()" error
     */
    public function summary()
    {
        $totalAssets = Asset::count();
        $totalCost = Asset::sum('cost');

        // Group assets by company for dashboard analytics
        $byCompany = Asset::with(['company', 'category'])
            ->get()
            ->groupBy('company_id')
            ->map(function ($items) {
                $companyName = $items->first()->company?->name ?? 'Unknown';
                return [
                    'company' => $companyName,
                    'asset_count' => $items->count(),
                    'total_cost' => $items->sum('cost'),
                    'categories' => $items->pluck('category.name')->unique()->implode(', '),
                ];
            })
            ->values();

        return response()->json([
            'totalAssets' => $totalAssets,
            'totalCost' => $totalCost,
            'byCompany' => $byCompany,
            'assets_with_codes' => AssetCode::count(),
        ]);
    }

    /**
     * SEARCH BY UNIQUE CODE
     */
    public function getAssetByUniqueCode(Request $request)
    {
        $request->validate([
            'unique_code' => 'required|string',
        ]);

        $assetCode = AssetCode::with(['asset.company', 'asset.category'])
            ->where('unique_code', $request->unique_code)
            ->first();

        if (!$assetCode || !$assetCode->asset) {
            return response()->json(['message' => 'Asset not found for this unique code.'], 404);
        }

        return response()->json([
            'unique_code' => $assetCode->unique_code,
            'asset' => $assetCode->asset,
        ]);
    }

    /**
     * UNIQUE CODE SUGGESTIONS (Auto-complete)
     */
    public function suggestUniqueCodes(Request $request)
    {
        $q = $request->query('q', '');
        $codes = AssetCode::where('unique_code', 'like', "%{$q}%")
            ->limit(10)
            ->pluck('unique_code');

        return response()->json($codes);
    }

    /**
     * STORE A NEW ASSET
     */
    public function store(Request $request)
    {
        $request->validate([
            'person_in_charge' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'category_id' => 'required|exists:categories,id',
            'cost' => 'nullable|numeric',
        ]);

        $asset = Asset::create($request->all());
        return response()->json($asset, 201);
    }

    /**
     * SAVE/ASSIGN UNIQUE CODE TO ASSET
     */
    public function saveUniqueCode(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'unique_code' => 'required|string|unique:asset_codes,unique_code',
        ]);

        $assetCode = AssetCode::create([
            'asset_id' => $request->asset_id,
            'unique_code' => $request->unique_code,
        ]);

        return response()->json([
            'message' => 'Unique code saved successfully',
            'data' => $assetCode,
        ], 201);
    }

    /**
     * DOWNLOAD/GENERATE QR TAG
     */
    public function downloadTag($unique_code)
    {
        $assetCode = AssetCode::with(['asset.company'])->where('unique_code', $unique_code)->first();

        if (!$assetCode) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $qrText = "Code: {$unique_code}\n"
                . "Owner: {$assetCode->asset->person_in_charge}\n"
                . "Company: " . ($assetCode->asset->company->name ?? 'N/A');

        $qrImage = QrCode::format('png')->size(300)->margin(2)->generate($qrText);

        return response($qrImage)->header('Content-Type', 'image/png');
    }

    /**
     * STANDARD RESOURCE METHODS (SHOW, UPDATE, DESTROY)
     */
    public function show(Asset $asset) { return $asset->load('company', 'category', 'assetCode'); }

    public function update(Request $request, Asset $asset) {
        $asset->update($request->all());
        return response()->json($asset);
    }

    public function destroy(Asset $asset) {
        $asset->delete();
        return response()->json(null, 204);
    }
}