<?php
namespace App\Http\Controllers;

use App\Models\ServerAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ActivityLog;

class ServerAccountController extends Controller
{
    // GET - Fetch all records with optional search
    public function index(Request $request): JsonResponse
    {
        $query = ServerAccount::select('id', 'name', 'department', 'server_user', 'server_password', 'status', 'remarks', 'company_id');
        
        // Add company filter
        if ($request->has('company_id') && !empty($request->company_id)) {
            $query->where('company_id', $request->company_id);
        }
        
        // Add search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('department', 'LIKE', "%{$searchTerm}%");
            });
        }
        
        // Add status filter (optional)
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }
        
        // Add department filter (optional)
        if ($request->has('department') && !empty($request->department)) {
            $query->where('department', $request->department);
        }
        
        $servers = $query->orderBy('id', 'desc')->get();
        
        return response()->json($servers);
    }
    
    // GET - Fetch single record
    public function show($id): JsonResponse
    {
        $server = ServerAccount::find($id);
        
        if (!$server) {
            return response()->json(['error' => 'Record not found'], 404);
        }
        
        return response()->json($server);
    }
    
    // POST - Create new record
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'server_user' => 'required|string|max:255',
            'server_password' => 'nullable|string|max:255',
            'status' => 'required|string|max:50',
            'remarks' => 'nullable|string',
        ]);
        
        // Always set company_id to default value
        $validated['company_id'] = 1; // or auth()->user()->company_id if user-based
        
        $server = ServerAccount::create($validated);
        
         // Log the activity (hide sensitive data)
        $logData = $server->toArray();
        if (isset($logData['password'])) {
            $logData['password'] = '***HIDDEN***';
        }

        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'created',
            'module' => 'Server Account',
            'record_id' => $server->id,
            'old_data' => null,
            'new_data' => $logData,
        ]);

        return response()->json($server, 201);
    }
    
    // PUT - Update existing record
    public function update(Request $request, $id): JsonResponse
    {
        $server = ServerAccount::find($id);
        
        if (!$server) {
            return response()->json(['error' => 'Record not found'], 404);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'server_user' => 'required|string|max:255',
            'server_password' => 'nullable|string|max:255',
            'status' => 'required|string|max:50',
            'remarks' => 'nullable|string',
        ]);
        
        // If password is empty during update, keep the existing one
        if (!isset($validated['server_password']) || empty($validated['server_password'])) {
            unset($validated['server_password']);
        }
        
        $server->update($validated);

        $oldData = $server->toArray();
        if (isset($oldData['password'])) {
            $oldData['password'] = '***HIDDEN***';
        }

        $server->update($request->all());

        $newData = $server->toArray();
        if (isset($newData['password'])) {
            $newData['password'] = '***HIDDEN***';
        }

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'updated',
            'module' => 'Server Account',
            'record_id' => $server->id,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
        
        return response()->json($server);
    }
    
    // DELETE
    public function destroy($id): JsonResponse
    {
        $server = ServerAccount::find($id);
        
        if (!$server) {
            return response()->json(['error' => 'Record not found'], 404);
        }
        
        $server->delete();

        $oldData = $server->toArray();
        if (isset($oldData['password'])) {
            $oldData['password'] = '***HIDDEN***';
        }

        $server->delete();

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'deleted',
            'module' => 'Server Account',
            'record_id' => $server->id,
            'old_data' => $oldData,
            'new_data' => null,
        ]);

        
        return response()->json(['message' => 'Record deleted successfully']);
    }
}