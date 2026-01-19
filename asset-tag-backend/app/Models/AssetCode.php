<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetCode extends Model
{
    use HasFactory;

    protected $fillable = ['asset_id', 'unique_code'];

    public $timestamps = false;

    public function asset()
    {
        // Point to the new table/model
        return $this->belongsTo(AssetInventory::class, 'asset_id', 'id');
    }
}