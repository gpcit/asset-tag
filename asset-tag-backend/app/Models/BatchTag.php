<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BatchTag extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'batch_tags';

    protected $fillable = [
        'asset_id',
        'unique_code',
        'file_path',
        'print_status',
    ];

     protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    public $timestamps = false;
}
