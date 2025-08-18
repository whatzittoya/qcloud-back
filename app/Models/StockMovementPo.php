<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovementPo extends Model
{
    protected $table = 'stock_movement_po';

    protected $fillable = [
        'date',
        'po'
    ];

    protected $casts = [
        'date' => 'date'
    ];
}
