<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug'];

    // Optional: if you have assets/inventory linked
    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
