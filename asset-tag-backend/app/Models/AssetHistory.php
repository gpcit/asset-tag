<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetHistory extends Model
{
    protected $fillable = [
        'asset_id',
        'employee_id',
        'department',
        'date_deployed',
        'date_returned',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
