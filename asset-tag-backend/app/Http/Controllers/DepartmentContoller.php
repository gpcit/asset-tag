<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
class DepartmentContoller extends Controller
{
    // List all the department
    public function index()
    {
         // Returns array of objects: {id, name}, sorted by name
        return Department::whereNotNull('name')
                       ->where('name', '!=', '')
                       ->orderBy('name')
                       ->get(['id', 'name']); 
    }


    //  Creating Department
     public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:departments,name',
        ]);

        $data['name'] = trim($data['name']);

        $department = Department::create($data);

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'created',
            'module' => 'Department',
            'record_id' => $department->id,
            'old_data' => null,
            'new_data' => [
                'name' => $department->name,
            ],
        ]);

        return response()->json([
            'id' => $department->id,
            'name' => $department->name
        ], 201);
    }

    public function destroy($id)
    {
        $department = Department::findOrFail($id);

        $oldData = [
            'name' => $department->name,
        ];

        $department->delete();

        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'deleted',
            'module' => 'Department',
            'record_id' => $id,
            'old_data' => $oldData,
            'new_data' => null,
        ]);

        return response()->json([
            'message' => 'Department deleted successfully'
        ]);
    }
}
