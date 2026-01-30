<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssetInventory;
use App\Models\AssetCode;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AssetController extends Controller
{
    /**
     * LIST ASSETS
     */
    public function index(Request $request)
    {
        $query = AssetInventory::with([
            'company',
            'category',
            'assetCode',
            'employee' // ✅ LOAD EMPLOYEE
        ]);

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

        $assetCode = AssetCode::with([
            'asset.company',
            'asset.category',
            'asset.employee'  // ✅ employee is the current owner
        ])
        ->where('unique_code', $request->unique_code)
        ->first();

        if (!$assetCode || !$assetCode->asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        return response()->json([
            'unique_code' => $assetCode->unique_code,
            'asset' => $assetCode->asset, // ✅ current owner only
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
            'person_in_charge_id' => 'nullable|exists:employees,id',
            'company_id' => 'required|exists:companies,id',
            'category_id' => 'required|exists:categories,id',
            'cost' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'specs' => 'nullable|string',
            'asset_info' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:255',
            'date_deployed' => 'nullable|date',
            'date_returned' => 'nullable|date',
            'remarks' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $asset = AssetInventory::create($data);

        return response()->json(
            $asset->load(['company', 'category', 'employee']),
            201
        );
    }

    
    //   UPDATE ASSET
     
    public function update(Request $request, AssetInventory $asset)
    {
        $data = $request->validate([
            'person_in_charge_id' => 'nullable|exists:employees,id',
            'department' => 'nullable|string|max:255',
            'date_deployed' => 'nullable|date',
            'date_returned' => 'nullable|date',
            'is_active' => 'sometimes|boolean',

            // other editable fields
            'company_id' => 'sometimes|exists:companies,id',
            'category_id' => 'sometimes|exists:categories,id',
            'cost' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'specs' => 'nullable|string',
            'asset_info' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        /**
         * =====================================================
         *  CASE 1: ASSET IS BEING RETURNED
         * =====================================================
         */
        if (!empty($data['date_returned'])) {

            // Save current owner to history (if exists)
            if ($asset->person_in_charge_id) {
                \DB::table('asset_histories')->insert([
                    'asset_id'      => $asset->id,
                    'employee_id'   => $asset->person_in_charge_id,
                    'department'    => $asset->department,
                    'date_deployed' => $asset->date_deployed,
                    'date_returned' => $data['date_returned'],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            // Clear ownership
            $data['person_in_charge_id'] = null;
            $data['department']          = null;
            $data['date_deployed']       = null;

            //  auto inactive
            $data['is_active'] = false;
        }

        /**
         * =====================================================
         *  CASE 2: ASSET IS BEING ASSIGNED
         * =====================================================
         */
        if (!empty($data['person_in_charge_id'])) {

            // Fresh deployment → always clear return
            $data['date_returned'] = null;

            // If user didn't pick a date, default to today
            if (empty($data['date_deployed'])) {
                $data['date_deployed'] = now()->format('Y-m-d');
            }

            //  auto active
            $data['is_active'] = true;
        }

        $asset->update($data);

        return response()->json(
            $asset->load([
                'company',
                'category',
                'assetCode',
                'employee',
                'histories.employee',
            ])
        );
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
        $assetCode = AssetCode::with([
            'asset.company',
            'asset.category', // ✅ load category
        ])
        ->where('unique_code', $unique_code)
        ->firstOrFail();

        $asset = $assetCode->asset;

        $qrText =
            "Unique Code: {$assetCode->unique_code}\n" .
            "Company: " . ($asset->company?->name ?? 'N/A') . "\n" .
            "Category: " . ($asset->category?->name ?? 'N/A') . "\n" .
            "Invoice Date: " . ($asset->invoice_date ?? 'N/A') . "\n" .
            "Specs: " . ($asset->specs ?? 'N/A');

        return response(
            QrCode::format('png')
                ->size(300)
                ->margin(2)
                ->generate($qrText)
        )->header('Content-Type', 'image/png');
    }

    /**
     * SHOW ASSET
     */
    public function show(AssetInventory $asset)
    {
        return response()->json(
            $asset->load([
                'company',
                'category',
                'assetCode',
                'employee',
                'histories.employee',
            ])
        );
    }

    /**
     * DELETE ASSET
     */
    public function destroy(AssetInventory $asset)
    {
        $asset->delete();
        return response()->json(null, 204);
    }

    /**
     * ASSET LIST (ACTIVE)
     */
    public function assetList()
    {
        $assets = AssetInventory::with(['company', 'employee'])
            ->where('is_active', 1)
            ->get();

        return response()->json($assets);
    }

    /**
     * ASSET LIST (ALL)
     */
    public function assetListAll()
    {
        $assets = AssetInventory::with(['company', 'employee'])->get();

        return response()->json($assets);
    }

    
}
