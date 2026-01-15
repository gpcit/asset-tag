<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;

class AssetController extends Controller
{
    public function index()
    {
        return Asset::with(['company', 'category'])->get();
    }

    protected function convertCamelToSnake(array $data): array
    {
        return collect($data)->mapWithKeys(function ($value, $key) {
            $snakeKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
            return [$snakeKey => $value];
        })->toArray();
    }

    public function store(Request $request)
    {
        $request->validate([
            'personInCharge' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'companyId' => 'required|exists:companies,id',
            'categoryId' => 'required|exists:categories,id',
            'cost' => 'nullable|numeric',
            'invoiceDate' => 'nullable|date',
            'dateDeployed' => 'nullable|date',
        ]);

        $data = $this->convertCamelToSnake($request->all());

        $asset = Asset::create($data);

        return response()->json($asset, 201);
    }

    public function show(Asset $asset)
    {
        return $asset->load('company', 'category');
    }

    public function update(Request $request, Asset $asset)
    {
        $request->validate([
            'personInCharge' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'companyId' => 'required|exists:companies,id',
            'categoryId' => 'required|exists:categories,id',
            'cost' => 'nullable|numeric',
            'invoiceDate' => 'nullable|date',
            'dateDeployed' => 'nullable|date',
        ]);

        $data = $this->convertCamelToSnake($request->all());

        $asset->update($data);

        return response()->json($asset);
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();
        return response()->json(null, 204);
    }
}
