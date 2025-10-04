<?php


namespace App\Http\Controllers;

use App\Models\Client as ModelsClient;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StockMovementPo;
use App\Models\Warehouse;
use App\Services\QuinosApiService;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Client;

class WarehouseController extends Controller
{
    private $client;
    private $cookieJar;
    private $quinosAPI;
    private $username;
    private $password;
    public function __construct()
    {
        // $this->middleware('auth');

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

    private function parseInt($value)
    {
        try {
            return intval($value);
        } catch (\Throwable $e) {
            return 0;
        }
    }
    function calcListed($stock)
    {
        $listed = 0;
        $opening = $this->parseInt($stock['opening']);
        $sales = $this->parseInt($stock['sales']);
        $received = $this->parseInt($stock['received']);
        $released = $this->parseInt($stock['released']);
        $transfer_in = $this->parseInt($stock['transfer_in']);
        $transfer_out = $this->parseInt($stock['transfer_out']);
        $waste = $this->parseInt($stock['waste']);
        $production = $this->parseInt($stock['production']);
        $calculated = $this->parseInt($stock['calculated']);
        $onhand = $this->parseInt($stock['onhand']);
        $listed = $opening + $received - $sales - $released + $transfer_in - $transfer_out - $waste + $production - $calculated - $onhand;
        return $listed;
    }
    private function parseStockData($data, $name)
    {
        $result = [];

        foreach ($data as $item) {
            $categoryName = $item['Category']['name'];
            // No grouping by category, just flatten items
            $result[] = [
                'code' => $item['Item']['code'],
                'name' => $item['Item']['name'],
                $name => $this->calcListed($item['Stock']),
                'category' => $categoryName
            ];
        }

        return array_values($result);
    }

    public function getStockMovement($date, Request $request)
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

            // Check if refresh flag is set
            $refresh = $request->has('refresh') && $request->refresh === 'true';
            // Check if data exists for the specific date with stock level join
            $stockMovements = StockMovement::leftJoin('stock_minimum', function ($join) {
                $join->on('stock_movement.item_id', '=', 'stock_minimum.item_id')
                    ->on('stock_movement.warehouse_id', '=', 'stock_minimum.warehouse_id');
            })
                ->select('stock_movement.*', 'stock_minimum.minimum')
                ->where('stock_movement.stock_movement_date', $date)
                ->whereIn('stock_movement.warehouse_id', $warehouses->pluck('warehouse_id'))
                ->get();

            // If refresh flag is set or no data exists for this date, sync first
            if ($refresh || $stockMovements->isEmpty()) {
                $stockLevelController = new StockLevelController();
                // Use the private method directly for better performance
                $warehouses = Warehouse::where('client_id', Auth::user()->client_id)->where('warehouse_id', 2677)->get();


                $syncResult = $stockLevelController->syncStockMovementDataForDate($date, $warehouses, $refresh);

                // Fetch data again after sync
                $stockMovements = StockMovement::leftJoin('stock_minimum', function ($join) {
                    $join->on('stock_movement.item_id', '=', 'stock_minimum.item_id')
                        ->on('stock_movement.warehouse_id', '=', 'stock_minimum.warehouse_id');
                })
                    ->select('stock_movement.*', 'stock_minimum.minimum')
                    ->where('stock_movement.stock_movement_date', $date)
                    ->whereIn('stock_movement.warehouse_id', $warehouses->pluck('warehouse_id'))
                    ->get();

                if ($stockMovements->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No stock movement data found for the specified date after sync'
                    ], 404);
                }
            }

            // Get PO IDs from purchase_orders table for this date
            $poIds = PurchaseOrder::where('stock_movement_date', $date)
                ->pluck('po_id')
                ->toArray();

            // Get need_to_order data for this date
            $needToOrderData = StockMovementPo::where('date', $date)
                ->pluck('need_to_order', 'item_code')
                ->toArray();
            // return  $needToOrderData;

            // Group data by item_id
            $groupedData = [];
            foreach ($stockMovements as $stock) {
                $itemId = $stock->item_id;

                if (!isset($groupedData[$itemId])) {
                    $groupedData[$itemId] = [
                        'item_id' => $itemId,
                        'code' => $stock->item_code,
                        'name' => $stock->item_name,
                        'category' => $stock->item_category,
                        'po' => 0,
                        'need_to_order' => $needToOrderData[$stock->item_code] ?? 0
                    ];
                }

                // Add warehouse data
                $warehouseName = $warehouses->where('warehouse_id', $stock->warehouse_id)->first()->name;
                $minimum = $stock->minimum ?? 0;
                $requested = $stock->listed - $minimum;

                $groupedData[$itemId][$warehouseName] = $stock->listed;
                $groupedData[$itemId][$warehouseName . '-requested'] = $requested;

                // Sum PO quantities across all warehouses
                $groupedData[$itemId]['po'] += $stock->po ?? 0;
            }

            // Convert to array and add 0 for warehouses without data
            $result = [];
            foreach ($groupedData as $itemId => $itemData) {
                $row = [
                    'code' => $itemData['code'],
                    'name' => $itemData['name'],
                    'category' => $itemData['category'],
                    'po' => $itemData['po'],
                    'need_to_order' => $itemData['need_to_order']
                ];

                // Add warehouse columns
                foreach ($warehouses as $warehouse) {
                    $warehouseName = $warehouse->name;
                    $row[$warehouseName] = $itemData[$warehouseName] ?? 0;
                    $row[$warehouseName . '-requested'] = $itemData[$warehouseName . '-requested'] ?? 0;
                }

                $result[] = $row;
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'date' => $date,
                'po_ids' => $poIds,
                'warehouses' => $warehouses->map(function ($warehouse) {
                    return [
                        'id' => $warehouse->warehouse_id,
                        'name' => $warehouse->name
                    ];
                }),
                'total_records' => count($result)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stock movement: ' . $e->getMessage()
            ], 500);
        }
    }




    public function warehouseList()
    {
        $warehouses = Warehouse::where('client_id', Auth::user()->client_id)->get();

        $parsedWarehouses = [];
        foreach ($warehouses as $warehouse) {
            $parsedWarehouses[] = [
                'id' => $warehouse->warehouse_id,
                'name' => $warehouse->name
            ];
        }

        return response()->json($parsedWarehouses);
    }

    public function getPurchaseOrders()
    {
        try {
            $api_url = "{$this->quinosAPI}/purchaseOrders/load/search:/closed:0";
            $response = json_decode($this->guzzleReq($api_url), true);

            if (!$response || !isset($response['result'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch purchase orders from API'
                ], 500);
            }

            $poList = [];
            foreach ($response['result'] as $po) {
                if (isset($po['PurchaseOrder']['id'])) {
                    $poList[] = [
                        'po_id' => $po['PurchaseOrder']['id'],
                        'supplier_name' => $po['Supplier']['name'] ?? '',
                        'date' => $po['PurchaseOrder']['date'] ?? '',
                        'total' => $po['PurchaseOrder']['total'] ?? '0'
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $poList,
                'total' => count($poList)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching purchase orders: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createOrUpdatePo(Request $request)
    {
        try {
            // Join array of po_ids with semicolon delimiter
            $poString = implode(';', $request->po_ids);
            $stock_date = $request->date;
            // Create or update PO record for the date
            $poRecord = StockMovementPo::updateOrCreate(
                ['date' => $request->date],
                ['po' => $poString]
            );

            return response()->json([
                'success' => true,
                'message' => $poRecord->wasRecentlyCreated ? 'PO created successfully' : 'PO updated successfully',
                'data' => [
                    'date' => $poRecord->date,
                    'po' => $poRecord->po,
                    'po_ids' => $request->po_ids
                ]
            ], $poRecord->wasRecentlyCreated ? 201 : 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating/updating PO: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getPO(Request $request)
    {
        $poId = $request->po_ids;
        $stock_date = $request->stock_date;

        // Get data from API
        $quinosApiService = new QuinosApiService();
        $apiData = $quinosApiService->getPurchaseOrderPreview($poId);
        // Store to database
        $poService = new PurchaseOrderService();
        $result = $poService->storeOrUpdatePo($apiData, $stock_date);

        return response()->json([
            'success' => true,
            'message' => 'PO stored successfully',
            'data' => $result
        ]);
    }

    public function syncPosForStockDate(Request $request)
    {
        $poIds = $request->po_ids; // Array of PO IDs
        $stock_date = $request->stock_date;

        $poService = new PurchaseOrderService();
        $result = $poService->syncMultiplePosForStockDate($poIds, $stock_date);

        return response()->json([
            'success' => true,
            'message' => "Synced POs for stock date {$stock_date}",
            'data' => $result
        ]);
    }

    public function updateNeedToOrder(Request $request)
    {
        try {
            $date = $request->date;
            $itemCode = $request->item_code;
            $needToOrder = $request->need_to_order;

            // Create or update the need_to_order record
            $poRecord = StockMovementPo::updateOrCreate(
                [
                    'date' => $date,
                    'item_code' => $itemCode
                ],
                [
                    'need_to_order' => $needToOrder
                ]
            );

            return response()->json([
                'success' => true,
                'message' => $poRecord->wasRecentlyCreated ? 'Need to order created successfully' : 'Need to order updated successfully',
                'data' => [
                    'date' => $poRecord->date,
                    'item_code' => $poRecord->item_code,
                    'need_to_order' => $poRecord->need_to_order
                ]
            ], $poRecord->wasRecentlyCreated ? 201 : 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating need to order: ' . $e->getMessage()
            ], 500);
        }
    }




    public function login()
    {
        $client = new Client();

        // Define login credentials


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
        // echo $response->getBody()->getContents();
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
