<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\BatchTag;

class BatchTagController extends Controller
{
    
    //    GET ALL TAGS (NOT DELETED)
    
    public function index()
    {
        $tags = BatchTag::whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $tags->each(function ($tag) {
            $tag->url = asset('storage/' . $tag->file_path);
        });

        return response()->json($tags);
    }

    
    //    STORE TAG
    
    public function store(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|integer',
            'unique_code' => 'required|string|max:50',
            'tag_image' => 'required|file|mimes:png|max:2048',
        ]);

        $path = $request->file('tag_image')->storeAs(
            'batch-tags',
            $request->unique_code . '.png',
            'public'
        );

        $batchTag = BatchTag::create([
            'asset_id' => $request->asset_id,
            'unique_code' => $request->unique_code,
            'file_path' => $path,
            'print_status' => 'not_printed'
        ]);

        $batchTag->url = asset('storage/' . $batchTag->file_path);

        return response()->json([
            'success' => true,
            'batch_tag' => $batchTag,
        ]);
    }

    
    //    SOFT DELETE
    
    public function destroy($id)
    {
        $tag = BatchTag::findOrFail($id);
        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag soft-deleted.'
        ]);
    }

    
    //    MARK ALL AS PRINTED
    
    public function markPrinted()
    {
        BatchTag::whereNull('deleted_at')
            ->where('print_status', 'not_printed')
            ->update([
                'print_status' => 'printed'
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All tags marked as printed.'
        ]);
    }
}
