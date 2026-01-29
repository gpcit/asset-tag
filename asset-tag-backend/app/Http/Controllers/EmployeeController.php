<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    // List employees with search & pagination
    public function index(Request $request)
    {
        $query = Employee::query();

        // Search by name or department (frontend sends 'q')
        if ($request->has('q') && $request->q) {
            $q = $request->q;
            $query->where(function($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                        ->orWhere('department', 'like', "%{$q}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $employees = $query->orderBy('name', 'asc')->paginate($perPage, ['*'], 'page', $page);

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

        return response()->json($employee);
    }

    // Soft delete employee
    public function destroy(Employee $employee)
    {
        $employee->delete(); // soft delete
        return response()->json(null, 204);
    }
}
