<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'warehouse_id',
        'client_id'
    ];

    protected $casts = [
        'client_id' => 'integer',
    ];
}
