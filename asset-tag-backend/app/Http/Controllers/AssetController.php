<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AssetInventory;
use App\Models\AssetCode;
use App\Models\Category;
use App\Models\Companies; // make sure this matches your model
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AssetController extends Controller
{
    /**
     * LIST ASSETS
     */
    public function index(Request $request)
    {
        $query = AssetInventory::with(['company', 'category', 'assetCode']);

        if ($request->boolean('has_unique_code')) {
            $query->whereHas('assetCode');
        }

        return response()->json($query->get());
    }

    /**
     * DASHBOARD SUMMARY
     */
    public function summary()
    {
        $totalAssets = AssetInventory::count();
        $totalCost = AssetInventory::sum('cost');

        $byCompany = AssetInventory::with(['company', 'category'])
            ->get()
            ->groupBy('company_id')
            ->map(function ($items) {
                $companyName = $items->first()->company?->name ?? 'Unknown';
                return [
                    'company' => $companyName,
                    'asset_count' => $items->count(),
                    'total_cost' => $items->sum('cost') ?? 0,
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
            return response()->json(['message' => 'Asset not found'], 404);
        }

        return response()->json([
            'unique_code' => $assetCode->unique_code,
            'asset' => $assetCode->asset,
        ]);
    }

    /**
     * UNIQUE CODE AUTOCOMPLETE
     */
    public function suggestUniqueCodes(Request $request)
    {
        return AssetCode::where('unique_code', 'like', '%' . $request->query('q', '') . '%')
            ->limit(10)
            ->pluck('unique_code');
    }

    /**
     * STORE ASSET
     */
    
    public function store(Request $request)
    {
        $data = $request->validate([
            'person_in_charge' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'category_id' => 'required|exists:categories,id',
            'cost' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'specs' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:255',
            'date_deployed' => 'nullable|date',
            'date_returned'=> 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $asset = AssetInventory::create($data);

        return response()->json($asset, 201);
    }

    public function update(Request $request, AssetInventory $asset)
    {
        $data = $request->validate([
            'person_in_charge' => 'sometimes|required|string|max:255',
            'department' => 'sometimes|required|string|max:255',
            'company_id' => 'sometimes|required|exists:companies,id',
            'category_id' => 'sometimes|required|exists:categories,id',
            'cost' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'specs' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:255',
            'date_deployed' => 'nullable|date',
            'date_returned'=> 'nullable|date',
            'remarks' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        // Auto-set date_returned if marking inactive
        if (array_key_exists('is_active', $data)) {
            if (!$data['is_active'] && !$asset->date_returned) {
                $data['date_returned'] = now()->format('Y-m-d'); // YYYY-MM-DD
            }

            // Optional: clearing date_returned if reactivating
            if ($data['is_active'] && $asset->date_returned) {
                $data['date_returned'] = null;
            }
        }

        $asset->update($data);

        return response()->json($asset);
    }




    /**
     * ASSIGN UNIQUE CODE
     */
    public function saveUniqueCode(Request $request)
    {
        $data = $request->validate([
            'asset_id' => 'required|exists:asset_inventories,id',
            'unique_code' => 'required|string|unique:asset_codes,unique_code',
        ]);

        $assetCode = AssetCode::create($data);

        return response()->json([
            'message' => 'Unique code saved',
            'data' => $assetCode,
        ], 201);
    }

    /**
     * DOWNLOAD QR TAG
     */
    public function downloadTag($unique_code)
    {
        $assetCode = AssetCode::with(['asset.company'])
            ->where('unique_code', $unique_code)
            ->firstOrFail();

        $asset = $assetCode->asset;

        $qrText =
            "Code: {$assetCode->unique_code}\n" .
            "Owner: {$asset->person_in_charge}\n" .
            "Company: " . ($asset->company->name ?? 'N/A');

        return response(
            QrCode::format('png')->size(300)->margin(2)->generate($qrText)
        )->header('Content-Type', 'image/png');
    }

    /**
     * SHOW ASSET
     */
    public function show(AssetInventory $asset)
    {
        return $asset->load('company', 'category', 'assetCode');
    }

    /**
     * DELETE ASSET
     */
    public function destroy(AssetInventory $asset)
    {
        $asset->delete(); // soft delete, sets deleted_at
        return response()->json(null, 204);
    }

    public function assetList()
    {
        $assets = AssetInventory::with('company')
            ->where('is_active', 1)
            ->get(['id', 'person_in_charge', 'company_id', 'is_active']);

        $result = $assets->map(function ($asset) {
            return [
                'id' => $asset->id,
                'person_in_charge' => $asset->person_in_charge,
                'company' => $asset->company?->name ?? 'N/A',
                'is_active' => $asset->is_active,
            ];
        });

        return response()->json($result)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
    public function assetListAll()
    {
        $assets = AssetInventory::with('company')
            ->get(['id', 'person_in_charge', 'company_id', 'is_active']);
        
        $result = $assets->map(function ($asset) {
            return [
                'id' => $asset->id,
                'person_in_charge' => $asset->person_in_charge,
                'company' => $asset->company?->name ?? 'N/A',
                'is_active' => $asset->is_active,
            ];
        });
        
        return response()->json($result)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
