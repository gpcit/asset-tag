<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
        $category->delete();

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
    //     $category->restore();

    //     return response()->json([
    //         'message' => 'Category restored successfully'
    //     ]);
    // }
}
