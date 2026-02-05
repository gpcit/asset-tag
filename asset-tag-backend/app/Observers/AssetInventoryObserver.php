<?php

namespace App\Observers;

use App\Models\AssetInventory;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class AssetInventoryObserver
{
    public function created(AssetInventory $asset)
    {
        ActivityLog::create([
            'user_id'    => Auth::id() ?? 1,
            'user_name'  => Auth::user()?->name ?? 'System',
            'user_role'  => Auth::user()?->role ?? 'admin',
            'action'     => 'create',
            'module'     => 'asset',
            'record_id'  => $asset->id,
            'old_data'   => null,
            'new_data'   => $asset->toArray(),
        ]);
    }

    // ✅ Remove update logging here to prevent double entries
    public function updated(AssetInventory $asset)
    {
        // Do nothing; handled in controller
    }

    public function deleted(AssetInventory $asset)
    {
        ActivityLog::create([
            'user_id'    => Auth::id() ?? 1,
            'user_name'  => Auth::user()?->name ?? 'System',
            'user_role'  => Auth::user()?->role ?? 'admin',
            'action'     => 'delete',
            'module'     => 'asset',
            'record_id'  => $asset->id,
            'old_data'   => $asset->toArray(),
            'new_data'   => null,
        ]);
    }
}
