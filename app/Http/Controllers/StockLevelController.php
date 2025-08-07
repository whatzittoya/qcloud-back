<?php

namespace App\Http\Controllers;

use App\Models\Client as ModelsClient;
use App\Models\Warehouse;
use App\Models\StockMinimum;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Client;

class StockLevelController extends Controller
{
    private $client;
    private $cookieJar;
    private $quinosAPI;
    private $username;
    private $password;

    public function __construct()
    {
        $this->middleware('auth');
        $client = ModelsClient::where('id', Auth::user()->client_id)->first();
        if (!$client) {
            return abort(404);
        }
        $client_name = $client->name;
        $cookieFile = __DIR__ . '/' . $client_name . '.txt';

        // Create a Guzzle client instance
        $this->client = new Client();

        // Create a FileCookieJar to handle cookies and store them in the specified file
        $this->cookieJar = new FileCookieJar($cookieFile, true);
        $this->quinosAPI = $client->url;
        $this->username = $client->email;
        $this->password = $client->password;
    }

    public function syncWarehousesToDatabase()
    {
        $api_url = "{$this->quinosAPI}/warehouses/combobox";
        $warehouses = json_decode($this->guzzleReq($api_url), true);

        if (!$warehouses) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouses from API'
            ], 500);
        }

        try {
            $updatedCount = 0;
            $createdCount = 0;

            foreach ($warehouses as $warehouse_id => $name) {
                $warehouse = Warehouse::updateOrCreate(
                    ['warehouse_id' => $warehouse_id],
                    [
                        'name' => $name,
                        'client_id' => Auth::user()->client_id
                    ]
                );

                if ($warehouse->wasRecentlyCreated) {
                    $createdCount++;
                } else {
                    $updatedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Warehouses synced successfully. Created: {$createdCount}, Updated: {$updatedCount}",
                'data' => [
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'total' => count($warehouses)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing warehouses: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncStockLevel($warehouse_id)
    {
        // If warehouse_id is "all", get all warehouses for the current client
        if ($warehouse_id === 'all') {
            $warehouses = Warehouse::where('client_id', Auth::user()->client_id)->get();

            if ($warehouses->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No warehouses found for this client'
                ], 404);
            }

            $totalCreated = 0;
            $totalUpdated = 0;
            $warehouseResults = [];

            foreach ($warehouses as $warehouse) {
                $api_url = "{$this->quinosAPI}/stocks/listStockLevel/{$warehouse->warehouse_id}";
                $stockLevel = json_decode($this->guzzleReq($api_url), true);

                if (!$stockLevel) {
                    $warehouseResults[] = [
                        'warehouse_id' => $warehouse->warehouse_id,
                        'warehouse_name' => $warehouse->name,
                        'status' => 'failed',
                        'message' => 'Failed to fetch data'
                    ];
                    continue;
                }

                $createdCount = 0;
                $updatedCount = 0;

                foreach ($stockLevel as $stock) {
                    $stockMinimum = StockMinimum::updateOrCreate(
                        [
                            'item_id' => $stock['Stock']['item_id'],
                            'warehouse_id' => $stock['Warehouse']['id']
                        ],
                        [
                            'name' => $stock['Item']['name'],
                            'minimum' => $stock['Stock']['minimum'],
                            'maximum' => $stock['Stock']['maximum']
                        ]
                    );

                    if ($stockMinimum->wasRecentlyCreated) {
                        $createdCount++;
                        $totalCreated++;
                    } else {
                        $updatedCount++;
                        $totalUpdated++;
                    }
                }

                $warehouseResults[] = [
                    'warehouse_id' => $warehouse->warehouse_id,
                    'warehouse_name' => $warehouse->name,
                    'status' => 'success',
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'total' => count($stockLevel)
                ];
            }

            return response()->json([
                'success' => true,
                'message' => "All stock levels synced successfully. Total Created: {$totalCreated}, Total Updated: {$totalUpdated}",
                'data' => [
                    'total_created' => $totalCreated,
                    'total_updated' => $totalUpdated,
                    'warehouses_processed' => count($warehouses),
                    'warehouse_results' => $warehouseResults
                ]
            ]);
        }

        // Single warehouse sync (existing logic)
        $api_url = "{$this->quinosAPI}/stocks/listStockLevel/{$warehouse_id}";
        $stockLevel = json_decode($this->guzzleReq($api_url), true);

        if (!$stockLevel) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock level from API'
            ], 500);
        }

        try {
            $updatedCount = 0;
            $createdCount = 0;

            foreach ($stockLevel as $stock) {
                $stockMinimum = StockMinimum::updateOrCreate(
                    [
                        'item_id' => $stock['Stock']['item_id'],
                        'warehouse_id' => $stock['Warehouse']['id']
                    ],
                    [
                        'name' => $stock['Item']['name'],
                        'minimum' => $stock['Stock']['minimum'],
                        'maximum' => $stock['Stock']['maximum']
                    ]
                );

                if ($stockMinimum->wasRecentlyCreated) {
                    $createdCount++;
                } else {
                    $updatedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Stock levels synced successfully. Created: {$createdCount}, Updated: {$updatedCount}",
                'data' => [
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'total' => count($stockLevel)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing stock levels: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStockMinimumDisplay()
    {
        try {
            // Get all warehouses for the current client
            $warehouses = Warehouse::where('client_id', Auth::user()->client_id)->get();

            if ($warehouses->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No warehouses found for this client'
                ], 404);
            }

            // Get all stock minimum data for this client's warehouses
            $warehouseIds = $warehouses->pluck('warehouse_id')->toArray();
            $stockMinimums = StockMinimum::whereIn('warehouse_id', $warehouseIds)->get();

            // Group by item_id
            $groupedData = [];
            $warehouseNames = [];

            // Create warehouse name mapping
            foreach ($warehouses as $warehouse) {
                $warehouseNames[$warehouse->warehouse_id] = $warehouse->name;
            }

            // Group stock minimum data by item_id
            foreach ($stockMinimums as $stock) {
                $itemId = $stock->item_id;

                if (!isset($groupedData[$itemId])) {
                    $groupedData[$itemId] = [
                        'name' => $stock->name,
                        'item_id' => $itemId,
                        'warehouses' => []
                    ];
                }

                $groupedData[$itemId]['warehouses'][$stock->warehouse_id] = [
                    'minimum' => $stock->minimum,
                    'maximum' => $stock->maximum
                ];
            }

            // Format the data for display
            $result = [];
            foreach ($groupedData as $itemId => $itemData) {
                $row = [
                    'name' => $itemData['name'],
                    'item_id' => $itemId
                ];

                // Add warehouse columns
                foreach ($warehouses as $warehouse) {
                    $warehouseId = $warehouse->warehouse_id;
                    $warehouseName = $warehouse->name;

                    if (isset($itemData['warehouses'][$warehouseId])) {
                        $stock = $itemData['warehouses'][$warehouseId];
                        $row[$warehouseName] = [
                            'minimum' => $stock['minimum'],
                            'maximum' => $stock['maximum']
                        ];
                    } else {
                        $row[$warehouseName] = [
                            'minimum' => 0,
                            'maximum' => 0
                        ];
                    }
                }

                $result[] = $row;
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'warehouses' => $warehouses->map(function ($warehouse) {
                    return [
                        'id' => $warehouse->warehouse_id,
                        'name' => $warehouse->name
                    ];
                }),
                'total_items' => count($result)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stock minimum display: ' . $e->getMessage()
            ], 500);
        }
    }
    public function syncStockMovementDate($date)
    {
        try {
            // Get all warehouses for the current client
            $warehouses = Warehouse::where('client_id', Auth::user()->client_id)->get();

            if ($warehouses->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No warehouses found for this client'
                ], 404);
            }

            $totalCreated = 0;
            $totalUpdated = 0;
            $warehouseResults = [];
            foreach ($warehouses as $warehouse) {
                $warehouseId = $warehouse->warehouse_id;

                // Check if data already exists for this warehouse and date
                $existingData = StockMovement::where('warehouse_id', $warehouseId)
                    ->where('stock_movement_date', $date)
                    ->first();
                if ($existingData) {
                    $warehouseResults[] = [
                        'warehouse_id' => $warehouseId,
                        'warehouse_name' => $warehouse->name,
                        'status' => 'skipped',
                        'message' => "Data already exists for {$date}"
                    ];
                    continue;
                }

                // Fetch data from API for this specific date
                $api_url = "{$this->quinosAPI}/stocks/getStockMovementReport/0/{$warehouseId}/{$date}";
                $stockMovement = json_decode($this->guzzleReq($api_url), true);

                if (!$stockMovement || !isset($stockMovement['stocks'])) {
                    $warehouseResults[] = [
                        'warehouse_id' => $warehouseId,
                        'warehouse_name' => $warehouse->name,
                        'status' => 'failed',
                        'message' => 'Failed to fetch data from API'
                    ];
                    continue;
                }

                $warehouseCreated = 0;
                $warehouseUpdated = 0;

                foreach ($stockMovement['stocks'] as $stock) {
                    $stockData = $stock['Stock'];
                    $itemData = $stock['Item'];
                    $categoryData = $stock['Category'];

                    // Calculate listed value using the same logic as calcListed function
                    $opening = intval($stockData['opening'] ?? 0);
                    $sales = intval($stockData['sales'] ?? 0);
                    $received = intval($stockData['received'] ?? 0);
                    $released = intval($stockData['released'] ?? 0);
                    $transfer_in = intval($stockData['transfer_in'] ?? 0);
                    $transfer_out = intval($stockData['transfer_out'] ?? 0);
                    $waste = intval($stockData['waste'] ?? 0);
                    $production = intval($stockData['production'] ?? 0);
                    $calculated = intval($stockData['calculated'] ?? 0);
                    $onhand = intval($stockData['onhand'] ?? 0);

                    $listed = $opening + $received - $sales - $released + $transfer_in - $transfer_out - $waste + $production - $calculated - $onhand;

                    $stockMovementRecord = StockMovement::updateOrCreate(
                        [
                            'item_id' => $itemData['id'],
                            'warehouse_id' => $warehouseId,
                            'stock_movement_date' => $date
                        ],
                        [
                            'item_code' => $itemData['code'],
                            'item_name' => $itemData['name'],
                            'item_category' => $categoryData['name'],
                            'opening' => $opening,
                            'sales' => $sales,
                            'received' => $received,
                            'released' => $released,
                            'transfer_in' => $transfer_in,
                            'transfer_out' => $transfer_out,
                            'waste' => $waste,
                            'production' => $production,
                            'calculated' => $calculated,
                            'onhand' => $onhand,
                            'listed' => $listed,
                            'stock_movement_date' => $date
                        ]
                    );

                    if ($stockMovementRecord->wasRecentlyCreated) {
                        $warehouseCreated++;
                        $totalCreated++;
                    } else {
                        $warehouseUpdated++;
                        $totalUpdated++;
                    }
                }

                $warehouseResults[] = [
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouse->name,
                    'status' => 'success',
                    'created' => $warehouseCreated,
                    'updated' => $warehouseUpdated,
                    'total' => count($stockMovement['stocks'])
                ];
            }

            return response()->json([
                'success' => true,
                'message' => "Stock movement synced for date {$date}. Total Created: {$totalCreated}, Total Updated: {$totalUpdated}",
                'data' => [
                    'date' => $date,
                    'total_created' => $totalCreated,
                    'total_updated' => $totalUpdated,
                    'warehouses_processed' => count($warehouses),
                    'warehouse_results' => $warehouseResults
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing stock movement for date: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncStockMovementDateRange($start_date, $end_date)
    {
        try {
            // Validate date format
            if (!strtotime($start_date) || !strtotime($end_date)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format. Use YYYY-MM-DD format.'
                ], 400);
            }

            // Validate date range
            if (strtotime($start_date) > strtotime($end_date)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Start date cannot be after end date.'
                ], 400);
            }

            // Get all warehouses for the current client
            $warehouses = Warehouse::where('client_id', Auth::user()->client_id)->get();

            if ($warehouses->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No warehouses found for this client'
                ], 404);
            }

            $totalCreated = 0;
            $totalUpdated = 0;
            $warehouseResults = [];
            $dateResults = [];

            foreach ($warehouses as $warehouse) {
                $warehouseId = $warehouse->warehouse_id;
                $warehouseCreated = 0;
                $warehouseUpdated = 0;
                $warehouseDatesProcessed = 0;
                $warehouseDatesSkipped = 0;
                $warehouseDatesFailed = 0;

                $currentDate = $start_date;
                while (strtotime($currentDate) <= strtotime($end_date)) {
                    // Check if data already exists for this warehouse and date
                    $existingData = StockMovement::where('warehouse_id', $warehouseId)
                        ->where('stock_movement_date', $currentDate)
                        ->first();

                    if ($existingData) {
                        $warehouseDatesSkipped++;
                        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                        continue;
                    }

                    // Fetch data from API for this specific date
                    $api_url = "{$this->quinosAPI}/stocks/getStockMovementReport/0/{$warehouseId}/{$currentDate}";
                    $stockMovement = json_decode($this->guzzleReq($api_url), true);

                    if (!$stockMovement || !isset($stockMovement['stocks'])) {
                        $warehouseDatesFailed++;
                        $dateResults[] = [
                            'date' => $currentDate,
                            'warehouse_id' => $warehouseId,
                            'warehouse_name' => $warehouse->name,
                            'status' => 'failed',
                            'message' => 'Failed to fetch data from API'
                        ];
                        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                        continue;
                    }

                    $dateCreated = 0;
                    $dateUpdated = 0;

                    foreach ($stockMovement['stocks'] as $stock) {
                        $stockData = $stock['Stock'];
                        $itemData = $stock['Item'];
                        $categoryData = $stock['Category'];

                        // Calculate listed value using the same logic as calcListed function
                        $opening = intval($stockData['opening'] ?? 0);
                        $sales = intval($stockData['sales'] ?? 0);
                        $received = intval($stockData['received'] ?? 0);
                        $released = intval($stockData['released'] ?? 0);
                        $transfer_in = intval($stockData['transfer_in'] ?? 0);
                        $transfer_out = intval($stockData['transfer_out'] ?? 0);
                        $waste = intval($stockData['waste'] ?? 0);
                        $production = intval($stockData['production'] ?? 0);
                        $calculated = intval($stockData['calculated'] ?? 0);
                        $onhand = intval($stockData['onhand'] ?? 0);

                        $listed = $opening + $received - $sales - $released + $transfer_in - $transfer_out - $waste + $production - $calculated - $onhand;

                        $stockMovementRecord = StockMovement::updateOrCreate(
                            [
                                'item_id' => $itemData['id'],
                                'warehouse_id' => $warehouseId,
                                'stock_movement_date' => $currentDate
                            ],
                            [
                                'item_code' => $itemData['code'],
                                'item_name' => $itemData['name'],
                                'item_category' => $categoryData['name'],
                                'opening' => $opening,
                                'sales' => $sales,
                                'received' => $received,
                                'released' => $released,
                                'transfer_in' => $transfer_in,
                                'transfer_out' => $transfer_out,
                                'waste' => $waste,
                                'production' => $production,
                                'calculated' => $calculated,
                                'onhand' => $onhand,
                                'listed' => $listed,
                                'stock_movement_date' => $currentDate
                            ]
                        );

                        if ($stockMovementRecord->wasRecentlyCreated) {
                            $dateCreated++;
                            $warehouseCreated++;
                            $totalCreated++;
                        } else {
                            $dateUpdated++;
                            $warehouseUpdated++;
                            $totalUpdated++;
                        }
                    }

                    $warehouseDatesProcessed++;
                    $dateResults[] = [
                        'date' => $currentDate,
                        'warehouse_id' => $warehouseId,
                        'warehouse_name' => $warehouse->name,
                        'status' => 'success',
                        'created' => $dateCreated,
                        'updated' => $dateUpdated,
                        'total' => count($stockMovement['stocks'])
                    ];

                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                }

                $warehouseResults[] = [
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouse->name,
                    'status' => 'completed',
                    'created' => $warehouseCreated,
                    'updated' => $warehouseUpdated,
                    'dates_processed' => $warehouseDatesProcessed,
                    'dates_skipped' => $warehouseDatesSkipped,
                    'dates_failed' => $warehouseDatesFailed,
                    'date_range' => "{$start_date} to {$end_date}"
                ];
            }

            return response()->json([
                'success' => true,
                'message' => "Stock movement synced for date range {$start_date} to {$end_date}. Total Created: {$totalCreated}, Total Updated: {$totalUpdated}",
                'data' => [
                    'date_range' => [
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ],
                    'total_created' => $totalCreated,
                    'total_updated' => $totalUpdated,
                    'warehouses_processed' => count($warehouses),
                    'warehouse_results' => $warehouseResults,
                    'date_results' => $dateResults
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing stock movement for date range: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAvailableMovementDates()
    {
        try {
            $query = StockMovement::query();
            $warehouse_id = 'all';
            // If warehouse_id is provided, filter by it
            if ($warehouse_id && $warehouse_id !== 'all') {
                $query->where('warehouse_id', $warehouse_id);
            } else {
                // Get warehouses for current client
                $warehouseIds = Warehouse::where('client_id', Auth::user()->client_id)
                    ->pluck('warehouse_id')
                    ->toArray();
                $query->whereIn('warehouse_id', $warehouseIds);
            }

            // Get distinct dates ordered by date
            $availableDates = $query->select('stock_movement_date')
                ->distinct()
                ->orderBy('stock_movement_date', 'desc')
                ->get()
                ->pluck('stock_movement_date')
                ->toArray();

            if (empty($availableDates)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No stock movement data found'
                ], 404);
            }

            // Get date range info
            $earliestDate = min($availableDates);
            $latestDate = max($availableDates);
            $totalDates = count($availableDates);

            // Get warehouse-specific date info if no specific warehouse requested
            $warehouseDateInfo = [];
            if (!$warehouse_id || $warehouse_id === 'all') {
                $warehouseIds = Warehouse::where('client_id', Auth::user()->client_id)
                    ->pluck('warehouse_id')
                    ->toArray();

                foreach ($warehouseIds as $whId) {
                    $warehouseDates = StockMovement::where('warehouse_id', $whId)
                        ->select('stock_movement_date')
                        ->distinct()
                        ->orderBy('stock_movement_date', 'desc')
                        ->get()
                        ->pluck('stock_movement_date')
                        ->toArray();

                    if (!empty($warehouseDates)) {
                        $warehouseDateInfo[] = [
                            'warehouse_id' => $whId,
                            'warehouse_name' => Warehouse::where('warehouse_id', $whId)->value('name'),
                            'earliest_date' => min($warehouseDates),
                            'latest_date' => max($warehouseDates),
                            'total_dates' => count($warehouseDates),
                            'date_range' => min($warehouseDates) . ' to ' . max($warehouseDates)
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'available_dates' => $availableDates,
                    'date_range' => [
                        'earliest_date' => $earliestDate,
                        'latest_date' => $latestDate,
                        'total_dates' => $totalDates
                    ],
                    'warehouse_date_info' => $warehouseDateInfo
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching available movement dates: ' . $e->getMessage()
            ], 500);
        }
    }
    public function syncStockMovement()
    {
        $warehouse_id = 'all';
        try {
            // If warehouse_id is "all" or null, get all warehouses for the current client
            if ($warehouse_id === 'all' || $warehouse_id === null) {
                $warehouses = Warehouse::where('client_id', Auth::user()->client_id)->get();

                if ($warehouses->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No warehouses found for this client'
                    ], 404);
                }
            } else {
                $warehouses = Warehouse::where('client_id', Auth::user()->client_id)
                    ->where('warehouse_id', $warehouse_id)
                    ->get();

                if ($warehouses->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Warehouse not found'
                    ], 404);
                }
            }

            $totalCreated = 0;
            $totalUpdated = 0;
            $warehouseResults = [];

            foreach ($warehouses as $warehouse) {
                $warehouseId = $warehouse->warehouse_id;

                // Check the latest date for this warehouse
                $latestRecord = StockMovement::where('warehouse_id', $warehouseId)
                    ->orderBy('stock_movement_date', 'desc')
                    ->first();

                if ($latestRecord) {
                    // Data exists, start from the day after the latest date
                    $startDate = date('Y-m-d', strtotime($latestRecord->stock_movement_date . ' +1 day'));
                    $status = 'updated';
                    $message = "Data updated from {$startDate}";
                } else {
                    // No data exists, start from August 1, 2025
                    $startDate = '2025-08-01';
                    $status = 'new';
                    $message = "New data collection from {$startDate}";
                }
                $endDate = date('Y-m-d');

                // Check if start date is in the future (no gap to fill)
                if (strtotime($startDate) > strtotime($endDate)) {
                    $warehouseResults[] = [
                        'warehouse_id' => $warehouseId,
                        'warehouse_name' => $warehouse->name,
                        'status' => 'up_to_date',
                        'message' => 'Data is up to date, no new data to collect'
                    ];
                    continue;
                }

                $warehouseCreated = 0;
                $warehouseUpdated = 0;
                $datesProcessed = 0;

                $currentDate = $startDate;


                while (strtotime($currentDate) <= strtotime($endDate)) {
                    $api_url = "{$this->quinosAPI}/stocks/getStockMovementReport/0/{$warehouseId}/{$currentDate}";
                    $stockMovement = json_decode($this->guzzleReq($api_url), true);

                    if ($stockMovement && isset($stockMovement['stocks'])) {
                        foreach ($stockMovement['stocks'] as $stock) {
                            $stockData = $stock['Stock'];
                            $itemData = $stock['Item'];
                            $categoryData = $stock['Category'];

                            // Calculate listed value using the same logic as calcListed function
                            $opening = intval($stockData['opening'] ?? 0);
                            $sales = intval($stockData['sales'] ?? 0);
                            $received = intval($stockData['received'] ?? 0);
                            $released = intval($stockData['released'] ?? 0);
                            $transfer_in = intval($stockData['transfer_in'] ?? 0);
                            $transfer_out = intval($stockData['transfer_out'] ?? 0);
                            $waste = intval($stockData['waste'] ?? 0);
                            $production = intval($stockData['production'] ?? 0);
                            $calculated = intval($stockData['calculated'] ?? 0);
                            $onhand = intval($stockData['onhand'] ?? 0);

                            $listed = $opening + $received - $sales - $released + $transfer_in - $transfer_out - $waste + $production - $calculated - $onhand;

                            $stockMovementRecord = StockMovement::updateOrCreate(
                                [
                                    'item_id' => $itemData['id'],
                                    'warehouse_id' => $warehouseId,
                                    'stock_movement_date' => $currentDate
                                ],
                                [
                                    'item_code' => $itemData['code'],
                                    'item_name' => $itemData['name'],
                                    'item_category' => $categoryData['name'],
                                    'opening' => $opening,
                                    'sales' => $sales,
                                    'received' => $received,
                                    'released' => $released,
                                    'transfer_in' => $transfer_in,
                                    'transfer_out' => $transfer_out,
                                    'waste' => $waste,
                                    'production' => $production,
                                    'calculated' => $calculated,
                                    'onhand' => $onhand,
                                    'listed' => $listed,
                                    'stock_movement_date' => $currentDate
                                ]
                            );

                            if ($stockMovementRecord->wasRecentlyCreated) {
                                $warehouseCreated++;
                                $totalCreated++;
                            } else {
                                $warehouseUpdated++;
                                $totalUpdated++;
                            }
                        }
                        $datesProcessed++;
                    }

                    // Move to next date
                    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                }

                $warehouseResults[] = [
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouse->name,
                    'status' => $status,
                    'message' => $message,
                    'created' => $warehouseCreated,
                    'updated' => $warehouseUpdated,
                    'dates_processed' => $datesProcessed,
                    'date_range' => "{$startDate} to {$endDate}"
                ];
            }

            return response()->json([
                'success' => true,
                'message' => "Stock movement synced successfully. Total Created: {$totalCreated}, Total Updated: {$totalUpdated}",
                'data' => [
                    'total_created' => $totalCreated,
                    'total_updated' => $totalUpdated,
                    'warehouses_processed' => count($warehouses),
                    'warehouse_results' => $warehouseResults
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing stock movement: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getLatestStockMovementDate($warehouse_id = null)
    {
        try {
            $query = StockMovement::query();

            // If warehouse_id is provided, filter by it
            if ($warehouse_id && $warehouse_id !== 'all') {
                $query->where('warehouse_id', $warehouse_id);
            } else {
                // Get warehouses for current client
                $warehouseIds = Warehouse::where('client_id', Auth::user()->client_id)
                    ->pluck('warehouse_id')
                    ->toArray();
                $query->whereIn('warehouse_id', $warehouseIds);
            }

            // Get the latest date
            $latestRecord = $query->orderBy('stock_movement_date', 'desc')
                ->first();

            if (!$latestRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'No stock movement data found'
                ], 404);
            }

            // Get latest date for each warehouse if no specific warehouse requested
            $warehouseDates = [];
            if (!$warehouse_id || $warehouse_id === 'all') {
                $warehouseIds = Warehouse::where('client_id', Auth::user()->client_id)
                    ->pluck('warehouse_id')
                    ->toArray();

                foreach ($warehouseIds as $whId) {
                    $latestForWarehouse = StockMovement::where('warehouse_id', $whId)
                        ->orderBy('stock_movement_date', 'desc')
                        ->first();

                    if ($latestForWarehouse) {
                        $warehouseDates[] = [
                            'warehouse_id' => $whId,
                            'warehouse_name' => Warehouse::where('warehouse_id', $whId)->value('name'),
                            'latest_date' => $latestForWarehouse->stock_movement_date,
                            'total_records' => StockMovement::where('warehouse_id', $whId)->count()
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'latest_date' => $latestRecord->stock_movement_date,
                    'warehouse_id' => $latestRecord->warehouse_id,
                    'warehouse_name' => Warehouse::where('warehouse_id', $latestRecord->warehouse_id)->value('name'),
                    'total_records' => $query->count(),
                    'warehouse_dates' => $warehouseDates
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching latest stock movement date: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login()
    {
        $client = new Client();

        // Define the login URL
        $loginUrl = "{$this->quinosAPI}/staffs/login";

        $client = new Client(array(
            'cookies' => $this->cookieJar,
        ));

        $response = $client->request('POST', $loginUrl, [
            'timeout' => 30,
            'form_params' => [
                'data[Staff][email]' => $this->username,
                'data[Staff][password]' => $this->password,
            ]
        ]);
        return str_contains($response->getBody()->getContents(), "Data Configuration");
    }

    public function guzzleReq($api_url)
    {
        $client = new Client();
        $response = $client->request('GET', $api_url, ['cookies' => $this->cookieJar]);
        $body = $response->getBody()->getContents();
        if (str_contains($body, 'Sign in')) {

            $login = $this->login();

            if ($login) {
                $response = $client->request('GET', $api_url, ['cookies' => $this->cookieJar]);
                $body = $response->getBody()->getContents();
            } else {
                $body = [];
            }
        }
        return $body;
    }
}
