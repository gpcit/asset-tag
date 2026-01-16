<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;

class AssetController extends Controller
{
    /**
     * List all assets with company and category relationships
     */
    public function index()
    {
        return Asset::with(['company', 'category'])->get();
    }

    /**
     * Store a new asset
     */
    public function store(Request $request)
    {
        // Validate snake_case payload from frontend
        $request->validate([
            'person_in_charge' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'category_id' => 'required|exists:categories,id',
            'cost' => 'nullable|numeric',
            'invoice_date' => 'nullable|date',
            'date_deployed' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'specifications' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        // Create the asset
        $asset = Asset::create($request->all());

        return response()->json($asset, 201);
    }

    /**
     * Show a single asset with relationships
     */
    public function show(Asset $asset)
    {
        return $asset->load('company', 'category');
    }

    /**
     * Update an asset
     */
    public function update(Request $request, Asset $asset)
    {
        $request->validate([
            'person_in_charge' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'category_id' => 'required|exists:categories,id',
            'cost' => 'nullable|numeric',
            'invoice_date' => 'nullable|date',
            'date_deployed' => 'nullable|date',
            'invoice_number' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'specifications' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $asset->update($request->all());

        return response()->json($asset);
    }

    /**
     * Delete an asset (soft delete if model uses SoftDeletes)
     */
    public function destroy(Asset $asset)
    {
        $asset->delete();
        return response()->json(null, 204);
    }

    /**
     * Dashboard summary
     */
    public function summary()
    {
        $totalAssets = Asset::count();
        $totalCost = Asset::sum('cost');

        // Group assets by company
        $byCompany = Asset::with(['company', 'category'])
            ->get()
            ->groupBy('company_id')
            ->map(function ($items, $companyId) {
                $companyName = $items->first()->company?->name ?? 'Unknown';
                $totalCost = $items->sum('cost');
                $categories = $items->pluck('category.name')->unique()->implode(', ');
                return [
                    'company' => $companyName,
                    'asset_count' => $items->count(),
                    'total_cost' => $totalCost,
                    'categories' => $categories,
                ];
            })
            ->values(); // reindex array

        return response()->json([
            'totalAssets' => $totalAssets,
            'totalCost' => $totalCost,
            'byCompany' => $byCompany,
        ]);
    }
}
