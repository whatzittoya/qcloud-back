<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_id',
        'supplier_id',
        'date',
        'no',
        'stock_movement_date',
        'warehouse_id',
        'total',
        'company_id',
        'closed'
    ];

    protected $casts = [
        'date' => 'date',
        'total' => 'decimal:2',
        'closed' => 'boolean'
    ];

    // Relationship to PO items
    public function items()
    {
        return $this->hasMany(PoItem::class, 'po_id', 'po_id');
    }
}
