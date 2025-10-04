<?php

namespace App\Http\Controllers;

use App\Models\StockMinimum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class StockMinimumController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $stockMinimums = StockMinimum::all();
            return response()->json([
                'success' => true,
                'data' => $stockMinimums,
                'message' => 'Stock minimums retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock minimums',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $warehouseId = null;
            if ($request->input('warehouse_name')) {
                $warehouse = \App\Models\Warehouse::where('name', $request->input('warehouse_name'))->first();
                $warehouseId = $warehouse ? $warehouse->id : null;
            }

            $stockMinimum = StockMinimum::create([
                'item_id' => $request->input('item_id'),
                'name' => $request->input('name'),
                'minimum' => $request->input('minimum'),
                'maximum' => $request->input('maximum'),
                'warehouse_id' => $warehouseId
            ]);

            return response()->json([
                'success' => true,
                'data' => $stockMinimum,
                'message' => 'Stock minimum created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock minimum',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $stockMinimum = StockMinimum::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $stockMinimum,
                'message' => 'Stock minimum retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stock mínimum not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $stockMinimum = StockMinimum::findOrFail($id);

            $warehouseId = null;
            if ($request->input('warehouse_name')) {
                $warehouse = \App\Models\Warehouse::where('name', $request->input('warehouse_name'))->first();
                $warehouseId = $warehouse ? $warehouse->id : null;
            }

            $stockMinimum->update(array_filter([
                'item_id' => $request->input('item_id'),
                'name' => $request->input('name'),
                'minimum' => $request->input('minimum'),
                'maximum' => $request->input('maximum'),
                'warehouse_id' => $warehouseId !== null ? $warehouseId : $request->input('warehouse_id')
            ], function ($value) {
                return $value !== null;
            }));

            return response()->json([
                'success' => true,
                'data' => $stockMinimum,
                'message' => 'Stock minimum updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock minimum',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $stockMinimum = StockMinimum::findOrFail($id);
            $stockMinimum->delete();

            return response()->json([
                'success' => true,
                'message' => 'Stock minimum deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stock minimum',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk insert multiple stock minimums.
     */
    public function bulkInsert(array $data): JsonResponse
    {
        try {
            $inserted = [];
            $errors = [];

            foreach ($data as $index => $item) {
                try {
                    $warehouseId = null;
                    if (isset($item['warehouse_name'])) {
                        $warehouse = \App\Models\Warehouse::where('name', $item['warehouse_name'])->first();
                        $warehouseId = $warehouse ? $warehouse->id : null;
                    }

                    $stockMinimum = StockMinimum::create([
                        'item_id' => $item['item_id'] ?? null,
                        'name' => $item['name'] ?? null,
                        'minimum' => $item['minimum'] ?? 0,
                        'maximum' => $item['maximum'] ?? 0,
                        'warehouse_id' => $warehouseId ?? $item['warehouse_id'] ?? null
                    ]);

                    $inserted[] = $stockMinimum;
                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $item,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $totalItems = count($data);
            $successCount = count($inserted);
            $errorCount = count($errors);

            return response()->json([
                'success' => $errorCount === 0,
                'data' => [
                    'inserted' => $inserted,
                    'errors' => $errors,
                    'summary' => [
                        'total' => $totalItems,
                        'successful' => $successCount,
                        'failed' => $errorCount
                    ]
                ],
                'message' => "Bulk insert completed: {$successCount}/{$totalItems} items inserted successfully"
            ], $errorCount > 0 ? 207 : 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk insert',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update multiple stock minimums.
     */
    public function bulkUpdate(array $data): JsonResponse
    {
        try {
            $updated = [];
            $errors = [];

            foreach ($data as $index => $item) {
                try {
                    if (!isset($item['id'])) {
                        throw new Exception("Missing ID for item at index {$index}");
                    }

                    $warehouseId = null;
                    if (isset($item['warehouse_name'])) {
                        $warehouse = \App\Models\Warehouse::where('name', $item['warehouse_name'])->first();
                        $warehouseId = $warehouse ? $warehouse->id : null;
                    }

                    $stockMinimum = StockMinimum::findOrFail($item['id']);
                    $updateData = array_filter([
                        'item_id' => $item['item_id'] ?? null,
                        'name' => $item['name'] ?? null,
                        'minimum' => $item['minimum'] ?? null,
                        'maximum' => $item['maximum'] ?? null,
                        'warehouse_id' => $warehouseId !== null ? $warehouseId : $item['warehouse_id'] ?? null
                    ], function ($value) {
                        return $value !== null;
                    });

                    $stockMinimum->update($updateData);
                    $updated[] = $stockMinimum->fresh();
                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'id' => $item['id'] ?? null,
                        'data' => $item,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $totalItems = count($data);
            $successCount = count($updated);
            $errorCount = count($errors);

            return response()->json([
                'success' => $errorCount === 0,
                'data' => [
                    'updated' => $updated,
                    'errors' => $errors,
                    'summary' => [
                        'total' => $totalItems,
                        'successful' => $successCount,
                        'failed' => $errorCount
                    ]
                ],
                'message' => "Bulk update completed: {$successCount}/{$totalItems} items updated successfully"
            ], $errorCount > 0 ? 207 : 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk upsert (insert or update) multiple stock minimums.
     */
    public function bulkUpsert(array $data): JsonResponse
    {
        try {
            $upserted = [];
            $errors = [];

            foreach ($data as $index => $item) {
                try {
                    $warehouseId = null;
                    if (isset($item['warehouse_name'])) {
                        $warehouse = \App\Models\Warehouse::where('name', $item['warehouse_name'])->first();
                        $warehouseId = $warehouse ? $warehouse->id : null;
                    }

                    $stockMinimum = StockMinimum::firstOrCreate(
                        ['name' => $item['name'] ?? null],
                        [
                            'item_id' => $item['item_id'] ?? null,
                            'minimum' => $item['minimum'] ?? 0,
                            'maximum' => $item['maximum'] ?? 0,
                            'warehouse_id' => $warehouseId ?? $item['warehouse_id'] ?? null
                        ]
                    );

                    // If record existed (wasn't recently created), update it
                    if (!$stockMinimum->wasRecentlyCreated) {
                        $stockMinimum->update([
                            'item_id' => $item['item_id'] ?? null,
                            'minimum' => $item['minimum'] ?? null,
                            'maximum' => $item['maximum'] ?? null,
                            'warehouse_id' => $warehouseId !== null ? $warehouseId : $item['warehouse_id'] ?? null
                        ]);
                    }

                    $upserted[] = $stockMinimum->fresh();
                } catch (Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $item,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $totalItems = count($data);
            $successCount = count($upserted);
            $errorCount = count($errors);

            return response()->json([
                'success' => $errorCount === 0,
                'data' => [
                    'upserted' => $upserted,
                    'errors' => $errors,
                    'summary' => [
                        'total' => $totalItems,
                        'successful' => $successCount,
                        'failed' => $errorCount
                    ]
                ],
                'message' => "Bulk upsert completed: {$successCount}/{$totalItems} items processed successfully"
            ], $errorCount > 0 ? 207 : 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk upsert',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
