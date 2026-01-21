<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServerAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'server_accounts'; 
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    protected $fillable = [
        'name',
        'department',
        'server_user',
        'server_password',
        'status',
        'remarks',
        'company_id'
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];
    public $timestamps = true;
}