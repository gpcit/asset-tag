<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Department extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    public function asset()
    {
        return $this->hasMany(AssetInventory::class);
    }
}
