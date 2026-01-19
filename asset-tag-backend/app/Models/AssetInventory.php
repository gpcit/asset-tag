<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetInventory extends Model
{
    use SoftDeletes;

    protected $table = 'asset_inventories';

    protected $fillable = [
        'person_in_charge',
        'department',
        'cost',
        'supplier',
        'model_number',
        'asset_info',
        'specs',
        'remarks',
        'company_id',
        'category_id',
        'invoice_date',
        'invoice_number',
        'date_deployed',
        'date_returned',
        'is_active',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'invoice_date' => 'date',
        'invoice_date' => 'date:Y-m-d',
        'date_deployed' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    protected $dates = ['invoice_date', 'date_deployed', 'date_returned', 'deleted_at'];

    // Enable timestamps
    public $timestamps = true;

    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function assetCode()
    {
        return $this->hasOne(AssetCode::class, 'asset_id', 'id');
    }
    public function getCostAttribute($value)
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
