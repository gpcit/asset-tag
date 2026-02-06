<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
class EmployeeController extends Controller
{
    public function show(Employee $employee)
    {
        return response()->json($employee);
    }
    public function all(Request $request)
    {
        $query = Employee::query();

        if ($q = $request->query('q')) {
            $query->where('name', 'like', "%$q%")
                ->orWhere('department', 'like', "%$q%");
        }

        $employees = $query->orderBy('name', 'asc')->get();

        return response()->json($employees);
    }

    // List employees with search & pagination
    public function index(Request $request)
    {
        $query = Employee::query();

        // Search filter
        if ($q = $request->query('q')) {
            $query->where('name', 'like', "%$q%")
                ->orWhere('department', 'like', "%$q%");
        }

        // Use paginate() instead of get()
        $perPage = $request->query('perPage', 10); // default 10
        $employees = $query->orderBy('name', 'asc')->paginate($perPage);

        // Return JSON with Laravel pagination structure
        return response()->json($employees);
    }

    // Store new employee
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
        ]);

        $data['is_active'] = 1; // default active
        $employee = Employee::create($data);

         // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'created',
            'module' => 'Employee',
            'record_id' => $employee->id,
            'old_data' => null,
            'new_data' => $employee->toArray(),
        ]);

        return response()->json($employee, 201);
    }

    // Update employee
    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'department' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $employee->update($data);

         // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'created',
            'module' => 'Employee',
            'record_id' => $employee->id,
            'old_data' => null,
            'new_data' => $employee->toArray(),
        ]);

        return response()->json($employee);
    }

    // Soft delete employee
    public function destroy(Employee $employee)
    {
        $employee->delete(); // soft delete


         // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'created',
            'module' => 'Employee',
            'record_id' => $employee->id,
            'old_data' => null,
            'new_data' => $employee->toArray(),
        ]);
        
        return response()->json(null, 204);
    }
}
