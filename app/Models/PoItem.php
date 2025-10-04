<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoItem extends Model
{
    protected $fillable = [
        'po_id',
        'item_id',
        'stock_movement_date',
        'quantity',
        'unit_price',
        'received'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'received' => 'integer',
        'stock_movement_date' => 'date'
    ];

    // Relationship to purchase order
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id', 'po_id');
    }
}
