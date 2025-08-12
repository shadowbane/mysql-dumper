<?php

namespace App\Models;

use App\Models\Traits\HasUlid32;
use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    use HasUlid32;

    protected $fillable = [
        'name',
        'host',
        'port',
        'database',
        'username',
        'password',
        'is_active',
        'skipped_tables',
        'structure_only',
    ];
    protected $hidden = [
        'password',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'password' => 'encrypted',
        'skipped_tables' => 'array',
        'structure_only' => 'array',
    ];
}
