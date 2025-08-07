<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovementPo extends Model
{
    protected $table = 'stock_movement_po';

    protected $fillable = [
        'item_id',
        'date',
        'po'
    ];

    protected $casts = [
        'item_id' => 'integer',
        'date' => 'date'
    ];
}
