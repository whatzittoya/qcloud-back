<?php


namespace App\Http\Controllers;

use App\Models\Client as ModelsClient;
use App\Models\StockMovement;
use App\Models\StockMovementPo;
use App\Models\Warehouse;

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

    public function getStockMovement($date)
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

            // Check if data exists for the specific date with stock level join
            $stockMovements = StockMovement::leftJoin('stock_minimum', function ($join) {
                $join->on('stock_movement.item_id', '=', 'stock_minimum.item_id')
                    ->on('stock_movement.warehouse_id', '=', 'stock_minimum.warehouse_id');
            })
                ->select('stock_movement.*', 'stock_minimum.minimum')
                ->where('stock_movement.stock_movement_date', $date)
                ->whereIn('stock_movement.warehouse_id', $warehouses->pluck('warehouse_id'))
                ->get();

            // If no data exists for this date, sync first
            if ($stockMovements->isEmpty()) {
                $stockLevelController = new StockLevelController();
                $syncResult = $stockLevelController->syncStockMovementDate($date);

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

            // Get PO data for the date
            $poData = StockMovementPo::where('date', $date)->get()->keyBy('item_id');

            // Group data by item_id
            $groupedData = [];
            foreach ($stockMovements as $stock) {
                $itemId = $stock->item_id;

                if (!isset($groupedData[$itemId])) {
                    $poRecord = $poData->get($itemId);
                    // return $poRecord;
                    $poValue = $poRecord ? $poRecord->po : '';

                    $groupedData[$itemId] = [
                        'item_id' => $itemId,
                        'code' => $stock->item_code,
                        'name' => $stock->item_name,
                        'category' => $stock->item_category,
                        'po' => $poValue
                    ];
                }

                // Add warehouse data
                $warehouseName = $warehouses->where('warehouse_id', $stock->warehouse_id)->first()->name;
                $minimum = $stock->minimum ?? 0;
                $requested = $stock->listed - $minimum;

                $groupedData[$itemId][$warehouseName] = $stock->listed;
                $groupedData[$itemId][$warehouseName . '-requested'] = $requested;
            }

            // Convert to array and add 0 for warehouses without data
            $result = [];
            foreach ($groupedData as $itemId => $itemData) {
                $row = [
                    'item_id' => $itemId,
                    'code' => $itemData['code'],
                    'name' => $itemData['name'],
                    'category' => $itemData['category'],
                    'po' => $itemData['po']
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
                'debug_po_count' => $poData->count(),
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

    public function storePo(Request $request)
    {
        try {
            $po = StockMovementPo::create([
                'item_id' => $request->item_id,
                'date' => $request->date,
                'po' => $request->po
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PO created successfully',
                'data' => $po
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating PO: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addPo(Request $request)
    {
        try {
            // Check if PO already exists for this item and date
            $existingPo = StockMovementPo::where('item_id', $request->item_id)
                ->where('date', $request->date)
                ->first();

            if ($existingPo) {
                // Update existing PO
                $existingPo->update(['po' => $request->po]);

                return response()->json([
                    'success' => true,
                    'message' => 'PO updated successfully',
                    'data' => $existingPo,
                    'action' => 'updated'
                ]);
            } else {
                // Create new PO
                $po = StockMovementPo::create([
                    'item_id' => $request->item_id,
                    'date' => $request->date,
                    'po' => $request->po
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'PO added successfully',
                    'data' => $po,
                    'action' => 'created'
                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding PO: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updatePo(Request $request, $id)
    {
        try {
            $po = StockMovementPo::findOrFail($id);
            $po->update([
                'po' => $request->po
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PO updated successfully',
                'data' => $po
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating PO: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deletePo($id)
    {
        try {
            $po = StockMovementPo::findOrFail($id);
            $po->delete();

            return response()->json([
                'success' => true,
                'message' => 'PO deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting PO: ' . $e->getMessage()
            ], 500);
        }
    }
}
