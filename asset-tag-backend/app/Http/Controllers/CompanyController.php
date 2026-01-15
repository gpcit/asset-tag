<?php

namespace App\Http\Controllers;

use App\Models\Companies;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies
     */
    public function index()
    {
       $companies = Companies::orderBy('name')->get();
       return response()->json($companies);
    }

    /**
     * Show the form for creating a new company
     */
    // public function create()
    // {
    //     return view('companies.create');
    // }

    // /**
    //  * Store a newly created company
    //  */
    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'name'       => 'required|string|max:255',
    //         'code'       => 'required|string|max:20|unique:companies,code',
    //         'contact_no' => 'nullable|string|max:100',
    //         'address'    => 'required|string|max:255',
    //         'location'   => 'required|string|max:100',
    //     ]);

    //     Companies::create($validated);

    //     return redirect()
    //         ->route('companies.index')
    //         ->with('success', 'Company added successfully.');
    // }

    // /**
    //  * Display the specified company
    //  */
    // public function show($id)
    // {
    //     $company = Companies::findOrFail($id);
    //     return view('companies.show', compact('company'));
    // }

    // /**
    //  * Show the form for editing the specified company
    //  */
    // public function edit($id)
    // {
    //     $company = Companies::findOrFail($id);
    //     return view('companies.edit', compact('company'));
    // }

    // /**
    //  * Update the specified company
    //  */
    // public function update(Request $request, $id)
    // {
    //     $company = Companies::findOrFail($id);

    //     $validated = $request->validate([
    //         'name'       => 'required|string|max:255',
    //         'code'       => 'required|string|max:20|unique:companies,code,' . $company->id,
    //         'contact_no' => 'nullable|string|max:100',
    //         'address'    => 'required|string|max:255',
    //         'location'   => 'required|string|max:100',
    //     ]);

    //     $company->update($validated);

    //     return redirect()
    //         ->route('companies.index')
    //         ->with('success', 'Company updated successfully.');
    // }

    // /**
    //  * Remove the specified company
    //  */
    // public function destroy($id)
    // {
    //     Companies::findOrFail($id)->delete();

    //     return redirect()
    //         ->route('companies.index')
    //         ->with('success', 'Company deleted successfully.');
    // }
}
