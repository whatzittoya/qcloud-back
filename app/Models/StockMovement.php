<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table = 'stock_movement';

    protected $fillable = [
        'item_id',
        'item_code',
        'item_name',
        'item_category',
        'warehouse_id',
        'opening',
        'sales',
        'received',
        'released',
        'transfer_in',
        'transfer_out',
        'waste',
        'production',
        'calculated',
        'onhand',
        'listed',
        'stock_movement_date',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'opening' => 'integer',
        'sales' => 'integer',
        'received' => 'integer',
        'released' => 'integer',
        'transfer_in' => 'integer',
        'transfer_out' => 'integer',
        'waste' => 'integer',
        'production' => 'integer',
        'calculated' => 'integer',
        'onhand' => 'integer',
        'listed' => 'integer',
        'stock_movement_date' => 'date'
    ];
}
