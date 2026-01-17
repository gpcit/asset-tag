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
        return $this->belongsTo(Asset::class, 'asset_id', 'id');
    }
}
