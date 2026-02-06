<?php

namespace App\Http\Controllers;

use App\Models\Companies;
use App\Models\ActivityLog;
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
            'location'   => 'required|string|max:255',
            'contact_no' => 'nullable|string|max:100',
            'address'    => 'nullable|string|max:255',
        ]);

        $company = Companies::create($validated);

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'created',
            'module' => 'Company',
            'record_id' => $company->id,
            'old_data' => null,
            'new_data' => $company->toArray(),
        ]);

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

        // Capture old data BEFORE updating
        $oldData = $company->toArray();

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:20|unique:companies,code,' . $company->id,
            'location'   => 'required|string|max:255',
            'contact_no' => 'nullable|string|max:100',
            'address'    => 'nullable|string|max:255',
        ]);

        $company->update($validated);

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'updated',  // Changed from 'created' to 'updated'
            'module' => 'Company',
            'record_id' => $company->id,
            'old_data' => $oldData,  // Old data before update
            'new_data' => $company->toArray(),
        ]);

        return response()->json($company);
    }

    /**
     * Remove the specified company (API)
     */
    public function destroy($id)
    {
        $company = Companies::findOrFail($id);

        // Capture old data BEFORE deleting
        $oldData = $company->toArray();

        $company->delete();

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'deleted',  // Changed from 'created' to 'deleted'
            'module' => 'Company',
            'record_id' => $id,
            'old_data' => $oldData,  // Old data before deletion
            'new_data' => null,  // No new data for deletions
        ]);

        return response()->json([
            'message' => 'Company deleted successfully'
        ]);
    }
}