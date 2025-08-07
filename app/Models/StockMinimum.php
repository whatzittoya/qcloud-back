<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMinimum extends Model
{
    protected $table = 'stock_minimum';

    protected $fillable = [
        'item_id',
        'name',
        'warehouse_id',
        'minimum',
        'maximum'
    ];

    protected $casts = [
        'item_id' => 'integer',
        'minimum' => 'integer',
        'maximum' => 'integer',
    ];
}
