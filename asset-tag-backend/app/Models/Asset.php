<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    // Specify which fields can be mass-assigned
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

    // Define the relationship to Companies
    public function company()
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
