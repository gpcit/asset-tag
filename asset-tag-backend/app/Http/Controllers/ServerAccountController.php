<?php

namespace App\Http\Controllers;

use App\Models\ServerAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServerAccountController extends Controller
{
    // GET - Fetch all records
    public function index(): JsonResponse
    {
        $servers = ServerAccount::select('id', 'name', 'department', 'server_user', 'server_password', 'status', 'remarks')
            ->orderBy('id', 'desc')
            ->get();
        
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
            'company_id' => 'nullable|integer',
        ]);

        $server = ServerAccount::create($validated);
        
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
            'company_id' => 'nullable|integer',
        ]);

        // If password is empty during update, keep the existing one
        if (!isset($validated['server_password']) || empty($validated['server_password'])) {
            unset($validated['server_password']);
        }

        $server->update($validated);
        
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
        
        return response()->json(['message' => 'Record deleted successfully']);
    }
}