<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Companies extends Model
{
    protected $table = 'companies';  // Singular table name
    
    protected $fillable = ['name', 'code', 'logo'];
    
    public $timestamps = false;
    
    public function assets()
    {
        return $this->hasMany(AssetInventory::class, 'company_id');
    }
}