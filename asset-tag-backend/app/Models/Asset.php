<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'person_in_charge',
        'department',
        'invoice_number',
        'invoice_date',
        'cost',
        'model_number',
        'supplier',
        'asset_info',
        'specifications',
        'date_deployed',
        'category_id',
        'company_id',
        'remarks',
    ];

    protected $dates = ['deleted_at'];

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    // IMPORTANT: relationship name
    public function assetCode()
    {
        return $this->hasOne(\App\Models\AssetCode::class, 'asset_id', 'id');
    }
}
