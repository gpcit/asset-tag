<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssetInventory;
use App\Models\AssetCode;
use App\Models\ActivityLog;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AssetController extends Controller
{
    /**
     * LIST ALL ASSETS
     */
    public function index(Request $request)
    {
        $query = AssetInventory::with([
            'company',
            'category',
            'assetCode',
            'employee',
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
            'asset.employee',
        ])
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

    /**
     * UPDATE ASSET
     */
    public function update(Request $request, AssetInventory $asset)
    {
        $data = $request->validate([
            'person_in_charge_id' => 'nullable|exists:employees,id',
            'department'          => 'nullable|string|max:255',
            'date_deployed'       => 'nullable|date',
            'date_returned'       => 'nullable|date',
            'is_active'           => 'sometimes|boolean',

            'company_id'          => 'sometimes|exists:companies,id',
            'category_id'         => 'sometimes|exists:categories,id',
            'cost'                => 'nullable|numeric|min:0',
            'supplier'            => 'nullable|string|max:255',
            'model_number'        => 'nullable|string|max:255',
            'specs'               => 'nullable|string',
            'asset_info'          => 'nullable|string',
            'invoice_date'        => 'nullable|date',
            'invoice_number'      => 'nullable|string|max:255',
            'remarks'             => 'nullable|string',
        ]);

        // Capture old values for logging
        $oldData = $asset->only(array_keys($data));

        // CASE 1: Asset is being returned
        if (!empty($data['date_returned'])) {
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

            $data['person_in_charge_id'] = null;
            $data['department']          = null;
            $data['date_deployed']       = null;
            $data['is_active']           = false;
        }

        // CASE 2: Asset is being assigned
        if (!empty($data['person_in_charge_id'])) {
            $data['date_returned'] = null;
            if (empty($data['date_deployed'])) {
                $data['date_deployed'] = now()->format('Y-m-d');
            }
            $data['is_active'] = true;
        }

        // Update asset
        $asset->update($data);

        // Capture changed fields
        $changes = $asset->getChanges();

        // Log update if something changed
        if (!empty($changes)) {
            ActivityLog::create([
                'user_id'   => auth()->id() ?? 1,
                'user_name' => auth()->user()?->name ?? 'System',
                'user_role' => auth()->user()?->role ?? 'admin',
                'action'    => 'update',
                'module'    => 'asset',
                'record_id' => $asset->id,
                'old_data'  => array_intersect_key($oldData, $changes),
                'new_data'  => $changes,
            ]);
        }

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
            'asset.category',
        ])->where('unique_code', $unique_code)->firstOrFail();

        $asset = $assetCode->asset;

        $qrText =
            "Unique Code: {$assetCode->unique_code}\n" .
            "Company: " . ($asset->company?->name ?? 'N/A') . "\n" .
            "Category: " . ($asset->category?->name ?? 'N/A') . "\n" .
            "Invoice Date: " . ($asset->invoice_date ?? 'N/A') . "\n" .
            "Specs: " . ($asset->specs ?? 'N/A');

        return response(
            QrCode::format('png')->size(300)->margin(2)->generate($qrText)
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

    public function destroyHistory($id)
    {
        try {
            $history = \DB::table('asset_histories')->where('id', $id)->first();
            
            if (!$history) {
                return response()->json(['message' => 'History entry not found'], 404);
            }

            // Check if already soft deleted
            if ($history->deleted_at !== null) {
                return response()->json(['message' => 'History entry already deleted'], 400);
            }
            
            // Log the deletion
            ActivityLog::create([
                'user_id'   => auth()->id() ?? 1,
                'user_name' => auth()->user()?->name ?? 'System',
                'user_role' => auth()->user()?->role ?? 'admin',
                'action'    => 'soft_delete',
                'module'    => 'asset_history',
                'record_id' => $id,
                'old_data'  => (array) $history,
                'new_data'  => ['deleted_at' => now()],
            ]);

            // Soft delete by setting deleted_at timestamp
            \DB::table('asset_histories')
                ->where('id', $id)
                ->update(['deleted_at' => now()]);

            return response()->json(['message' => 'History entry deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete history entry: ' . $e->getMessage()], 500);
        }
    }

    /**
 * UPDATE ASSET HISTORY ENTRY
 */
    public function updateHistory(Request $request, $id)
    {
        try {
            $history = \DB::table('asset_histories')->where('id', $id)->first();
            
            if (!$history) {
                return response()->json(['message' => 'History entry not found'], 404);
            }

            // Check if soft deleted
            if ($history->deleted_at !== null) {
                return response()->json(['message' => 'Cannot edit deleted history entry'], 400);
            }

            $data = $request->validate([
                'employee_id'   => 'nullable|exists:employees,id',
                'department'    => 'nullable|string|max:255',
                'date_deployed' => 'nullable|date',
                'date_returned' => 'nullable|date',
                'remarks'       => 'nullable|string',
            ]);

            // Capture old data for logging
            $oldData = (array) $history;

            // Update the history entry
            \DB::table('asset_histories')
                ->where('id', $id)
                ->update([
                    'employee_id'   => $data['employee_id'] ?? $history->employee_id,
                    'department'    => $data['department'] ?? $history->department,
                    'date_deployed' => $data['date_deployed'] ?? $history->date_deployed,
                    'date_returned' => $data['date_returned'] ?? $history->date_returned,
                    'remarks'       => $data['remarks'] ?? $history->remarks,
                    'updated_at'    => now(),
                ]);

            // Get updated history
            $updatedHistory = \DB::table('asset_histories')->where('id', $id)->first();

            // Log the update
            ActivityLog::create([
                'user_id'   => auth()->id() ?? 1,
                'user_name' => auth()->user()?->name ?? 'System',
                'user_role' => auth()->user()?->role ?? 'admin',
                'action'    => 'update',
                'module'    => 'asset_history',
                'record_id' => $id,
                'old_data'  => $oldData,
                'new_data'  => (array) $updatedHistory,
            ]);

            // Load employee data
            if ($updatedHistory->employee_id) {
                $updatedHistory->employee = \DB::table('employees')
                    ->where('id', $updatedHistory->employee_id)
                    ->first();
            }

            return response()->json([
                'message' => 'History entry updated successfully',
                'data' => $updatedHistory
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update history entry: ' . $e->getMessage()], 500);
        }
    }
    
}
