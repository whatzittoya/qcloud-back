<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovementPo extends Model
{
    protected $table = 'stock_movement_po';

    protected $fillable = [
        'date',
        'need_to_order',
        'item_code'
    ];

    protected $casts = [
        'date' => 'date',
        'need_to_order' => 'integer',
        'item_code' => 'integer'
    ];
}
