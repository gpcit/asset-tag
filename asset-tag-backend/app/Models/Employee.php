<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    // Table name (optional if Laravel naming conventions followed)
    protected $table = 'employees';

    // Fillable fields for mass assignment
    protected $fillable = [
        'name',
        'department',
        'is_active',
    ];

    // Default attributes
    protected $attributes = [
        'is_active' => 1,
    ];

    // Dates for soft deletes
    protected $dates = ['deleted_at'];
}
