<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\BatchTag;
use App\Models\ActivityLog;

class BatchTagController extends Controller
{
    /**
     * GET ALL TAGS (NOT DELETED)
     */
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

    /**
     * STORE TAG
     */
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

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'created',
            'module' => 'Batch Tag',
            'record_id' => $batchTag->id,
            'old_data' => null,
            'new_data' => [
                'unique_code' => $batchTag->unique_code,
                'asset_id' => $batchTag->asset_id,
                'print_status' => $batchTag->print_status,
            ],
        ]);

        return response()->json([
            'success' => true,
            'batch_tag' => $batchTag,
        ]);
    }

    /**
     * SOFT DELETE SINGLE TAG
     */
    public function destroy($id)
    {
        $tag = BatchTag::findOrFail($id);

        // Capture old data
        $oldData = [
            'unique_code' => $tag->unique_code,
            'asset_id' => $tag->asset_id,
            'print_status' => $tag->print_status,
        ];

        $tag->delete();

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'deleted',
            'module' => 'Batch Tag',
            'record_id' => $id,
            'old_data' => $oldData,
            'new_data' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tag soft-deleted.'
        ]);
    }

    /**
     * MARK SINGLE TAG AS PRINTED
     */
    public function markPrinted($id)
    {
        $tag = BatchTag::findOrFail($id);

        // Capture old data
        $oldData = [
            'unique_code' => $tag->unique_code,
            'print_status' => $tag->print_status,
        ];

        $tag->update(['print_status' => 'printed']);

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'marked as printed',
            'module' => 'Batch Tag',
            'record_id' => $tag->id,
            'old_data' => $oldData,
            'new_data' => [
                'unique_code' => $tag->unique_code,
                'print_status' => 'printed',
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tag marked as printed.'
        ]);
    }

    /**
     * DELETE ALL PRINTED TAGS (SOFT DELETE)
     */
    public function deletePrinted()
    {
        // Get all printed tags before deleting
        $printedTags = BatchTag::whereNull('deleted_at')
            ->where('print_status', 'printed')
            ->get();

        $deletedCount = $printedTags->count();
        $uniqueCodes = $printedTags->pluck('unique_code')->toArray();

        // Soft delete all tags where print_status is 'printed'
        BatchTag::whereNull('deleted_at')
            ->where('print_status', 'printed')
            ->delete();

        // Log the activity
        ActivityLog::create([
            'user_name' => auth()->user()->name ?? 'System',
            'user_role' => auth()->user()->role ?? 'system',
            'action' => 'bulk deleted printed tags',
            'module' => 'Batch Tag',
            'record_id' => null,
            'old_data' => [
                'deleted_count' => $deletedCount,
                'unique_codes' => implode(', ', $uniqueCodes),
            ],
            'new_data' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deletedCount} printed tags successfully.",
            'deleted_count' => $deletedCount
        ]);
    }
}