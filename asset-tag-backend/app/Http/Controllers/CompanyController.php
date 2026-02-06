<?php

namespace App\Http\Controllers;

use App\Models\Companies;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies (API)
     */
    public function index()
    {
        return response()->json(
            Companies::orderBy('name')->get()
        );
    }

    /**
     * Store a newly created company (API)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:20|unique:companies,code',
            'contact_no' => 'nullable|string|max:100',
            'address'    => 'required|string|max:255',
        ]);

        $company = Companies::create($validated);

        return response()->json($company, 201);
    }

    /**
     * Display the specified company (API)
     */
    public function show($id)
    {
        return response()->json(
            Companies::findOrFail($id)
        );
    }

    /**
     * Update the specified company (API)
     */
    public function update(Request $request, $id)
    {
        $company = Companies::findOrFail($id);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:20|unique:companies,code,' . $company->id,
            'contact_no' => 'nullable|string|max:100',
            'address'    => 'required|string|max:255',
        ]);

        $company->update($validated);

        return response()->json($company);
    }

    /**
     * Remove the specified company (API)
     */
    public function destroy($id)
    {
        $company = Companies::findOrFail($id);
        $company->delete();

        return response()->json([
            'message' => 'Company deleted successfully'
        ]);
    }
}