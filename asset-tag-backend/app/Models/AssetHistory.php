<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class AssetHistory extends Model
{
     use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'employee_id',
        'department',
        'date_deployed',
        'date_returned',
    ];

    public function asset()
    {
        return $this->belongsTo(AssetInventory::class, 'asset_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
