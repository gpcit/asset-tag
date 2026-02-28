<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssetInventory;
use App\Models\AssetCode;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Department;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;

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
            'department',
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
        $totalCost   = AssetInventory::sum('cost');

        $byCompany = AssetInventory::with(['company', 'category'])
            ->get()
            ->groupBy('company_id')
            ->map(function ($items) {
                $companyName = $items->first()->company?->name ?? 'Unknown';
                return [
                    'company'     => $companyName,
                    'asset_count' => $items->count(),
                    'total_cost'  => $items->sum('cost') ?? 0,
                    'categories'  => $items->pluck('category.name')->unique()->implode(', '),
                ];
            })
            ->values();

        return response()->json([
            'totalAssets'       => $totalAssets,
            'totalCost'         => $totalCost,
            'byCompany'         => $byCompany,
            // count() already excludes soft-deleted rows automatically
            'assets_with_codes' => AssetCode::count(),
        ]);
    }

    /**
     * SEARCH BY CONTROL NUMBER
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
        ->where('control_number', $request->unique_code)
        ->first();

        if (!$assetCode || !$assetCode->asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        return response()->json([
            'unique_code' => $assetCode->control_number,
            'asset'       => $assetCode->asset,
        ]);
    }

    /**
     * CONTROL NUMBER AUTOCOMPLETE
     */
    public function suggestUniqueCodes(Request $request)
    {
        return AssetCode::where('control_number', 'like', '%' . $request->query('q', '') . '%')
            ->limit(10)
            ->pluck('control_number');
    }

    /**
     * STORE ASSET
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'person_in_charge_id' => 'nullable|exists:employees,id',
            'department_id'       => 'nullable|exists:departments,id',
            'company_id'          => 'required|exists:companies,id',
            'category_id'         => 'required|exists:categories,id',
            'cost'                => 'nullable|numeric|min:0',
            'supplier'            => 'nullable|string|max:255',
            'model_number'        => 'nullable|string|max:255',
            'specs'               => 'nullable|string',
            'asset_info'          => 'nullable|string',
            'invoice_date'        => 'nullable|date',
            'invoice_number'      => 'nullable|string|max:255',
            'date_deployed'       => 'nullable|date',
            'date_returned'       => 'nullable|date',
            'remarks'             => 'nullable|string',
            'is_active'           => 'nullable|boolean',
        ]);

        if (!empty($data['person_in_charge_id'])) {
            $employee = Employee::find($data['person_in_charge_id']);

            $data['person_in_charge'] = $employee?->name;
            $data['date_deployed']    = now()->format('Y-m-d');

            if (empty($data['department_id'])) {
                $data['department_id'] = $employee?->department_id;
            }
        }

        $asset = AssetInventory::create($data);

        $assetCode = $this->generateControlNumberForAsset($asset);

        $this->downloadTag($assetCode->control_number);

        return response()->json(
            $asset->load(['company', 'category', 'employee', 'assetCode', 'department']),
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
            'department_id'       => 'nullable|exists:departments,id',
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

        $oldData = $asset->only(array_keys($data));

        /**
         * CASE 1: RETURN ASSET
         */
        if (!empty($data['date_returned'])) {

            if ($asset->person_in_charge_id) {
                DB::table('asset_histories')->insert([
                    'asset_id'      => $asset->id,
                    'employee_id'   => $asset->person_in_charge_id,
                    'department'    => $asset->department?->name,
                    'date_deployed' => $asset->date_deployed,
                    'date_returned' => $data['date_returned'],
                    'remarks'       => $data['remarks'] ?? $asset->remarks,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            $data['person_in_charge_id'] = null;
            $data['person_in_charge']    = null;
            $data['department_id']       = null;
            $data['date_deployed']       = null;
            $data['remarks']             = null;
        }

        /**
         * CASE 2: ASSIGN TO NEW EMPLOYEE
         */
        if (!empty($data['person_in_charge_id'])) {

            if ($asset->person_in_charge_id && $asset->person_in_charge_id != $data['person_in_charge_id']) {
                DB::table('asset_histories')->insert([
                    'asset_id'      => $asset->id,
                    'employee_id'   => $asset->person_in_charge_id,
                    'department'    => $asset->department?->name,
                    'date_deployed' => $asset->date_deployed,
                    'date_returned' => now()->format('Y-m-d'),
                    'remarks'       => $asset->remarks,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            $employee = Employee::find($data['person_in_charge_id']);

            $data['person_in_charge'] = $employee?->name;
            $data['date_returned']    = null;
            $data['date_deployed']    = $data['date_deployed'] ?? now()->format('Y-m-d');
            $data['remarks']          = $data['remarks'] ?? null;

            if (empty($data['department_id'])) {
                $data['department_id'] = $employee?->department_id;
            }
        }

        $asset->update($data);

        $changes = $asset->getChanges();

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
                'department',
                'histories.employee'
            ])
        );
    }

    /**
     * PRIVATE: GENERATE CONTROL NUMBER FOR AN ASSET
     */
    private function generateControlNumberForAsset(AssetInventory $asset): AssetCode
    {
        $asset->loadMissing(['company', 'category']);

        // Also check withTrashed so we don't create a duplicate
        //    for a previously soft-deleted code on the same asset
        $existing = AssetCode::withTrashed()->where('asset_id', $asset->id)->first();
        if ($existing) {
            // If it was soft-deleted, restore it instead of creating a new one
            if ($existing->trashed()) {
                $existing->restore();
            }
            return $existing;
        }

        $companyCode = strtoupper(trim($asset->company?->code ?? 'ASSET'));

        $categoryCode = strtoupper(
            substr(preg_replace('/[^a-zA-Z]/', '', $asset->category?->name ?? 'GEN'), 0, 3)
        );

        $sequence = AssetInventory::where('company_id', $asset->company_id)
            ->where('category_id', $asset->category_id)
            ->where(function ($query) use ($asset) {
                $query->where('created_at', '<', $asset->created_at)
                    ->orWhere(function ($q) use ($asset) {
                        $q->where('created_at', $asset->created_at)
                          ->where('id', '<=', $asset->id);
                    });
            })
            ->count();

        $controlNumber = $companyCode . '-' . $categoryCode . str_pad($sequence, 5, '0', STR_PAD_LEFT);

        // Also check withTrashed for uniqueness so we don't reuse
        //    a control number that belongs to a soft-deleted record
        if (AssetCode::withTrashed()->where('control_number', $controlNumber)->exists()) {
            $controlNumber = $companyCode . '-' . $categoryCode . '-' . str_pad($asset->id, 5, '0', STR_PAD_LEFT);
        }

        return AssetCode::create([
            'asset_id'       => $asset->id,
            'control_number' => $controlNumber,
        ]);
    }

    /**
     * GENERATE CONTROL NUMBER (API endpoint)
     */
    public function generateControlNumber($assetId)
    {
        return DB::transaction(function () use ($assetId) {
            $asset = AssetInventory::with(['company', 'category'])
                ->lockForUpdate()
                ->findOrFail($assetId);

            $assetCode = $this->generateControlNumberForAsset($asset);

            return response()->json([
                'unique_code' => $assetCode->control_number,
                'asset_id'    => $assetCode->asset_id,
            ]);
        });
    }

    /**
     * DOWNLOAD QR TAG
     */
    public function downloadTag($control_number)
    {
        $assetCode = AssetCode::with([
            'asset.company',
            'asset.category',
        ])->where('control_number', $control_number)->firstOrFail();

        $asset = $assetCode->asset;

        $qrText =
            "Control Number: {$assetCode->control_number}\n" .
            "Company: "      . ($asset->company?->name  ?? 'N/A') . "\n" .
            "Category: "     . ($asset->category?->name ?? 'N/A') . "\n" .
            "Invoice Date: " . ($asset->invoice_date    ?? 'N/A') . "\n" .
            "Specs: "        . ($asset->specs            ?? 'N/A');

        return response(
            QrCode::format('svg')->size(300)->margin(2)->generate($qrText)
        )->header('Content-Type', 'image/svg+xml');
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
                'department',
                'histories.employee',
            ])
        );
    }

    /**
     * DELETE ASSET
     *  Soft deletes the asset — the AssetInventory model's
     *    booted() observer cascades the soft delete to asset_codes.
     */
    public function destroy(AssetInventory $asset)
    {
        ActivityLog::create([
            'user_id'   => auth()->id() ?? 1,
            'user_name' => auth()->user()?->name ?? 'System',
            'user_role' => auth()->user()?->role ?? 'admin',
            'action'    => 'soft_delete',
            'module'    => 'asset',
            'record_id' => $asset->id,
            'old_data'  => $asset->toArray(),
            'new_data'  => ['deleted_at' => now()],
        ]);

        //  This triggers the deleting observer in AssetInventory
        //    which calls $asset->assetCode()->delete() automatically
        $asset->delete();

        return response()->json(null, 204);
    }

    /**
     * ASSET LIST (ACTIVE)
     */
    public function assetList()
    {
        $assets = AssetInventory::with(['company', 'employee', 'department'])
            ->where('is_active', 1)
            ->get();

        return response()->json($assets);
    }

    /**
     * ASSET LIST (ALL)
     */
    public function assetListAll()
    {
        $assets = AssetInventory::with(['company', 'employee', 'department'])->get();
        return response()->json($assets);
    }

    /**
     * DELETE ASSET HISTORY
     */
    public function destroyHistory($id)
    {
        try {
            $history = DB::table('asset_histories')->where('id', $id)->first();

            if (!$history) {
                return response()->json(['message' => 'History entry not found'], 404);
            }

            if ($history->deleted_at !== null) {
                return response()->json(['message' => 'History entry already deleted'], 400);
            }

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

            DB::table('asset_histories')
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
            $history = DB::table('asset_histories')->where('id', $id)->first();

            if (!$history) {
                return response()->json(['message' => 'History entry not found'], 404);
            }

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

            $oldData = (array) $history;

            DB::table('asset_histories')
                ->where('id', $id)
                ->update([
                    'employee_id'   => $data['employee_id']   ?? $history->employee_id,
                    'department'    => $data['department']    ?? $history->department,
                    'date_deployed' => $data['date_deployed'] ?? $history->date_deployed,
                    'date_returned' => $data['date_returned'] ?? $history->date_returned,
                    'remarks'       => $data['remarks']       ?? $history->remarks,
                    'updated_at'    => now(),
                ]);

            $updatedHistory = DB::table('asset_histories')->where('id', $id)->first();

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

            if ($updatedHistory->employee_id) {
                $updatedHistory->employee = DB::table('employees')
                    ->where('id', $updatedHistory->employee_id)
                    ->first();
            }

            return response()->json([
                'message' => 'History entry updated successfully',
                'data'    => $updatedHistory,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update history entry: ' . $e->getMessage()], 500);
        }
    }
}