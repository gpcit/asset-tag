<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'action',
        'module',
        'record_id',
        'old_data',
        'new_data',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    // Optional: Accessor to format changes
    public function getChangesReadableAttribute()
    {
        if (!$this->old_data || !$this->new_data) return [];

        $changes = [];

        foreach ($this->new_data as $key => $value) {
            $oldValue = $this->old_data[$key] ?? null;

            if ($oldValue != $value) {
                // Format booleans nicely
                if ($key === 'is_active') {
                    $oldValue = $oldValue ? 'Active' : 'Inactive';
                    $value = $value ? 'Active' : 'Inactive';
                }

                // Format dates nicely
                if (str_contains($key, 'date') && $oldValue) {
                    $oldValue = date('M d, Y', strtotime($oldValue));
                }
                if (str_contains($key, 'date') && $value) {
                    $value = date('M d, Y', strtotime($value));
                }

                $changes[] = ucfirst(str_replace('_', ' ', $key))
                    . " changed from '{$oldValue}' to '{$value}'";
            }
        }

        return $changes;
    }
}
    