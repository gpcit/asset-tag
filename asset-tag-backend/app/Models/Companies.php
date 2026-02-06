<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Companies extends Model
{
    use SoftDeletes;
    protected $table = 'companies';  // Singular table name
    
    protected $fillable = ['name', 'code', 'address', 'contact_no' ,'logo'];
    
    public $timestamps = false;
    
    public function assets()
    {
        return $this->hasMany(AssetInventory::class, 'company_id');
    }
}