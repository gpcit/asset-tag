<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * List all categories (id + name only)
     */
    public function index()
    {
        // Returns array of objects: {id, name}, sorted by name
        return Category::whereNotNull('name')
                       ->where('name', '!=', '')
                       ->orderBy('name')
                       ->get(['id', 'name']); 
    }

    /**
     * Store a new category
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:categories,name',
            'slug' => 'nullable|string|unique:categories,slug',
        ]);

        $data['name'] = trim($data['name']);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category = Category::create($data);

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'created',
            'module' => 'Category',
            'record_id' => $category->id,
            'old_data' => null,
            'new_data' => [
                'name' => $category->name,
                'slug' => $category->slug,
            ],
        ]);

        return response()->json([
            'id' => $category->id,
            'name' => $category->name
        ], 201);
    }

    /**
     * Soft delete a category
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // Capture old data before deletion
        $oldData = [
            'name' => $category->name,
            'slug' => $category->slug,
        ];

        $category->delete();

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'deleted',
            'module' => 'Category',
            'record_id' => $id,
            'old_data' => $oldData,
            'new_data' => null,
        ]);

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

    // /**
    //  * Restore a soft-deleted category (optional)
    //  */
    // public function restore($id)
    // {
    //     $category = Category::withTrashed()->findOrFail($id);
    //     
    //     $category->restore();
    //
    //     // Log the activity
    //     ActivityLog::create([
    //         'user_name' => auth()->user()->name ?? 'System',
    //         'user_role' => auth()->user()->role ?? 'system',
    //         'action' => 'restored',
    //         'module' => 'Category',
    //         'record_id' => $id,
    //         'old_data' => null,
    //         'new_data' => [
    //             'name' => $category->name,
    //             'slug' => $category->slug,
    //         ],
    //     ]);
    //
    //     return response()->json([
    //         'message' => 'Category restored successfully'
    //     ]);
    // }
}