<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PoItem;
use App\Models\StockMovement;

class PurchaseOrderService
{
    public function storeOrUpdatePo($apiResponse, $stock_date)
    {
        $poData = $apiResponse['PurchaseOrder'];
        $items = $apiResponse['PurchaseOrderLine'];

        // Store or update PO
        $po = PurchaseOrder::updateOrCreate(
            ['po_id' => $poData['id']],
            [
                'supplier_id' => $poData['supplier_id'],
                'date' => $poData['date'],
                'stock_movement_date' => $stock_date,
                'no' => $poData['no'],
                'warehouse_id' => $poData['warehouse_id'],
                'total' => $poData['total'],
                'company_id' => $poData['company_id'],
                'closed' => $poData['closed'] == '1'
            ]
        );

        // Store or update PO items
        foreach ($items as $item) {
            PoItem::updateOrCreate(
                [
                    'po_id' => $poData['id'],
                    'item_id' => $item['item_id'],
                    'stock_movement_date' => $stock_date
                ],
                [
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'received' => $item['received']
                ]
            );
        }

        // Update stock_movement with PO qty
        $this->updateStockMovementPoQty($stock_date, $items, $poData['warehouse_id']);

        return $po;
    }<?php

    namespace App\Services;
    
    use App\Models\PurchaseOrder;
    use App\Models\PoItem;
    use App\Models\StockMovement;
    
    class PurchaseOrderService
    {
        public function storeOrUpdatePo($apiResponse, $stock_date)
        {
            $poData = $apiResponse['PurchaseOrder'];
            $items = $apiResponse['PurchaseOrderLine'];
    
            // Store or update PO
            $po = PurchaseOrder::updateOrCreate(
                ['po_id' => $poData['id']],
                [
                    'supplier_id' => $poData['supplier_id'],
                    'date' => $poData['date'],
                    'stock_movement_date' => $stock_date,
                    'no' => $poData['no'],
                    'warehouse_id' => $poData['warehouse_id'],
                    'total' => $poData['total'],
                    'company_id' => $poData['company_id'],
                    'closed' => $poData['closed'] == '1'
                ]
            );
    
            // Store or update PO items
            foreach ($items as $item) {
                PoItem::updateOrCreate(
                    [
                        'po_id' => $poData['id'],
                        'item_id' => $item['item_id'],
                        'stock_movement_date' => $stock_date
                    ],
                    [
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'received' => $item['received']
                    ]
                );
            }
    
            // Update stock_movement with PO qty
            $this->updateStockMovementPoQty($stock_date, $items, $poData['warehouse_id']);
    
            return $po;
        }
    
        public function syncMultiplePosForStockDate($poIds, $stock_date)
        {
            $quinosApiService = new QuinosApiService();
            $totalProcessed = 0;
            $skipped = 0;
            $allItems = []; // Collect all items data
    
            // Reset/empty po column for this stock_date
            StockMovement::where('stock_movement_date', $stock_date)
                ->update(['po' => 0]);
    
            foreach ($poIds as $poId) {
                // Check if PO already exists
                $existingPo = PurchaseOrder::where('po_id', $poId)->first();
    
                if ($existingPo) {
                    // Update stock_movement_date if different
                    if ($existingPo->stock_movement_date != $stock_date) {
                        $existingPo->update(['stock_movement_date' => $stock_date]);
    
                        // Also update PoItem records for this PO
                        PoItem::where('po_id', $poId)
                            ->update(['stock_movement_date' => $stock_date]);
                    }
    
                    // Get existing items for this PO
                    $existingItems = PoItem::where('po_id', $poId)
                        ->where('stock_movement_date', $stock_date)
                        ->get();
    
                    foreach ($existingItems as $item) {
                        $allItems[] = [
                            'item_id' => $item->item_id,
                            'warehouse_id' => $item->purchaseOrder->warehouse_id,
                            'quantity' => $item->quantity
                        ];
                    }
                    $skipped++;
                    continue;
                }
    
                // Fetch PO from API
                $apiData = $quinosApiService->getPurchaseOrderPreview($poId);
    
                if ($apiData) {
                    // Store PO and items
                    $this->storeOrUpdatePo($apiData, $stock_date);
    
                    // Collect items data
                    foreach ($apiData['PurchaseOrderLine'] as $item) {
                        $allItems[] = [
                            'item_id' => $item['item_id'],
                            'warehouse_id' => $apiData['PurchaseOrder']['warehouse_id'],
                            'quantity' => $item['quantity']
                        ];
                    }
                    $totalProcessed++;
                }
            }
    
            // Add all quantities additively
            $this->addPoQuantitiesAdditively($allItems, $stock_date);
    
            return [
                'total_processed' => $totalProcessed,
                'skipped' => $skipped,
                'stock_date' => $stock_date
            ];
        }
    
        private function addPoQuantitiesAdditively($allItems, $stock_date)
        {
            // Group by item_id and warehouse_id, sum quantities
            $itemQuantities = [];
            foreach ($allItems as $item) {
                $key = $item['item_id'] . '_' . $item['warehouse_id'];
                if (!isset($itemQuantities[$key])) {
                    $itemQuantities[$key] = [
                        'item_id' => $item['item_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'total_qty' => 0
                    ];
                }
                $itemQuantities[$key]['total_qty'] += $item['quantity'];
            }
    
            // Update stock_movement with total quantities
            foreach ($itemQuantities as $data) {
                StockMovement::where('item_id', $data['item_id'])
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->where('stock_movement_date', $stock_date)
                    ->update(['po' => $data['total_qty']]);
            }
        }
    
        private function updateStockMovementPoQty($stock_date, $items, $warehouse_id)
        {
            foreach ($items as $item) {
                StockMovement::where('item_id', $item['item_id'])
                    ->where('stock_movement_date', $stock_date)
                    ->where('warehouse_id', $warehouse_id)
                    ->update(['po' => $item['quantity']]);
            }
        }
    }
    

    public function syncMultiplePosForStockDate($poIds, $stock_date)
    {
        $quinosApiService = new QuinosApiService();
        $totalProcessed = 0;
        $skipped = 0;
        $allItems = []; // Collect all items data

        // Reset/empty po column for this stock_date
        StockMovement::where('stock_movement_date', $stock_date)
            ->update(['po' => 0]);

        foreach ($poIds as $poId) {
            // Check if PO already exists
            $existingPo = PurchaseOrder::where('po_id', $poId)->first();

            if ($existingPo) {
                // Update stock_movement_date if different
                if ($existingPo->stock_movement_date != $stock_date) {
                    $existingPo->update(['stock_movement_date' => $stock_date]);

                    // Also update PoItem records for this PO
                    PoItem::where('po_id', $poId)
                        ->update(['stock_movement_date' => $stock_date]);
                }

                // Get existing items for this PO
                $existingItems = PoItem::where('po_id', $poId)
                    ->where('stock_movement_date', $stock_date)
                    ->get();

                foreach ($existingItems as $item) {
                    $allItems[] = [
                        'item_id' => $item->item_id,
                        'warehouse_id' => $item->purchaseOrder->warehouse_id,
                        'quantity' => $item->quantity
                    ];
                }
                $skipped++;
                continue;
            }

            // Fetch PO from API
            $apiData = $quinosApiService->getPurchaseOrderPreview($poId);

            if ($apiData) {
                // Store PO and items
                $this->storeOrUpdatePo($apiData, $stock_date);

                // Collect items data
                foreach ($apiData['PurchaseOrderLine'] as $item) {
                    $allItems[] = [
                        'item_id' => $item['item_id'],
                        'warehouse_id' => $apiData['PurchaseOrder']['warehouse_id'],
                        'quantity' => $item['quantity']
                    ];
                }
                $totalProcessed++;
            }
        }

        // Add all quantities additively
        $this->addPoQuantitiesAdditively($allItems, $stock_date);

        return [
            'total_processed' => $totalProcessed,
            'skipped' => $skipped,
            'stock_date' => $stock_date
        ];
    }

    private function addPoQuantitiesAdditively($allItems, $stock_date)
    {
        // Group by item_id and warehouse_id, sum quantities
        $itemQuantities = [];
        foreach ($allItems as $item) {
            $key = $item['item_id'] . '_' . $item['warehouse_id'];
            if (!isset($itemQuantities[$key])) {
                $itemQuantities[$key] = [
                    'item_id' => $item['item_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'total_qty' => 0
                ];
            }
            $itemQuantities[$key]['total_qty'] += $item['quantity'];
        }

        // Update stock_movement with total quantities
        foreach ($itemQuantities as $data) {
            StockMovement::where('item_id', $data['item_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->where('stock_movement_date', $stock_date)
                ->update(['po' => $data['total_qty']]);
        }
    }

    private function updateStockMovementPoQty($stock_date, $items, $warehouse_id)
    {
        foreach ($items as $item) {
            StockMovement::where('item_id', $item['item_id'])
                ->where('stock_movement_date', $stock_date)
                ->where('warehouse_id', $warehouse_id)
                ->update(['po' => $item['quantity']]);
        }
    }
}
