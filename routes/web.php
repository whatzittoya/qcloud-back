<?php

/** @var \Laravel\Lumen\Routing\Router $router */

use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

// $router->get('/', function () use ($router) {
//     return Hash::make('a');
//     return $router->app->version();
// });
$router->get('/', 'TransactionController@login');

$router->group(['prefix' => 'transaction'], function () use ($router) {
    $router->get('/', 'TransactionController@index');
    $router->get('/monthly/{store}', 'TransactionController@monthly');
    $router->get('/monthly', 'TransactionController@monthly');
    $router->get('/daily', 'TransactionController@daily');
    $router->get('/daily/{date1}/{date2}/{store}', 'TransactionController@daily');
    $router->get('/daily/{date1}/{date2}', 'TransactionController@daily');
    $router->get('/store', 'TransactionController@store');
});

$router->group(['prefix' => 'report'], function () use ($router) {
    $router->get('/payment-summary/{date1}/{date2}/{store}', 'ReportsController@paymentSummary');
    $router->get('/summary-sales/{date1}/{date2}/{store}', 'ReportsController@summarySales');
    $router->get('/discount-summary/{date1}/{date2}/{store}', 'ReportsController@discountSummary');
    $router->get('/no-sales/{date1}/{date2}/{store}', 'ReportsController@noSales');
    $router->get('/item-sales/{date1}/{date2}/{store}', 'ReportsController@itemSales');
    $router->get('/sales-type/{date1}/{date2}/{store}', 'ReportsController@salesType');
    $router->get('/consolidate/{month}/{year}/{store_f}', 'ReportsController@consolidateReport');
    $router->post('/consolidate-additional', 'ReportsController@storeConsolidateAdditional');
});

$router->group(['prefix' => 'warehouse'], function () use ($router) {
    $router->get('/list', 'WarehouseController@warehouseList');
    //stockmovement
    $router->get('/stock-movement/{date}', 'WarehouseController@getStockMovement');
    $router->get('/purchase-orders', 'WarehouseController@getPurchaseOrders');
    // $router->post('/po', 'WarehouseController@createOrUpdatePo');
    $router->get('/po-preview/{po_id}/{stock_date}', 'WarehouseController@getPO');
    $router->post('/po', 'WarehouseController@syncPosForStockDate');
    $router->post('/need-to-order', 'WarehouseController@updateNeedToOrder');
});

$router->group(['prefix' => 'stock-level'], function () use ($router) {
    $router->get('/sync-warehouses', 'StockLevelController@syncWarehousesToDatabase');
    $router->get('/sync/{warehouse_id}', 'StockLevelController@syncStockLevel');
    $router->get('/display', 'StockLevelController@getStockMinimumDisplay');

    $router->get('/sync-movement', 'StockLevelController@syncStockMovement');
    $router->get('/sync-movement-date/{date}', 'StockLevelController@syncStockMovementDate');
    $router->get('/sync-movement-range/{start_date}/{end_date}', 'StockLevelController@syncStockMovementDateRange');
    $router->get('/available-dates', 'StockLevelController@getAvailableMovementDates');
    $router->get('/latest-date/{warehouse_id?}', 'StockLevelController@getLatestStockMovementDate');
});

$router->group(['prefix' => 'stock-minimum'], function () use ($router) {
    $router->get('/', 'StockMinimumController@index');
    $router->post('/', 'StockMinimumController@store');
    $router->get('/{id}', 'StockMinimumController@show');
    $router->put('/{id}', 'StockMinimumController@update');
    $router->delete('/{id}', 'StockMinimumController@destroy');

    // Bulk operations
    $router->post('/bulk-insert', function (\Illuminate\Http\Request $request) {
        $controller = new App\Http\Controllers\StockMinimumController();
        return $controller->bulkInsert($request->input('data', []));
    });
    $router->put('/bulk-update', function (\Illuminate\Http\Request $request) {
        $controller = new App\Http\Controllers\StockMinimumController();
        return $controller->bulkUpdate($request->input('data', []));
    });
    $router->post('/bulk-upsert', function (\Illuminate\Http\Request $request) {
        $controller = new App\Http\Controllers\StockMinimumController();
        return $controller->bulkUpsert($request->input('data', []));
    });
});

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->get('/daily-transaction/{date1}/{date2}/{store}', 'DailyTransactionController@index');
});
$router->group(['prefix' => 'client'], function () use ($router) {
    $router->get('/', 'ClientController@index');
    $router->post('/', 'ClientController@store');
    $router->post('/update', 'ClientController@update');
    $router->post('/delete/{id}', 'ClientController@destroy');
    $router->get('/store', 'StoreController@index');
    $router->post('/store', 'StoreController@update');
    $router->get('/store/admin/{client}', 'StoreController@getAdmin');
});
$router->group(['prefix' => 'super_admin'], function () use ($router) {
    $router->post('/change-client', 'UserController@changeClientId');
});


$router->post('/login-back', 'UserController@authenticate');
$router->post('/logout-back', ['uses' => 'UserController@logout', 'middleware' => 'auth']);
$router->post('/reset-pass', ['uses' => 'UserController@resetPass', 'middleware' => 'auth']);
$router->post('/add-member', ['uses' => 'UserController@addMember', 'middleware' => 'auth']);
$router->get('/get-member', ['uses' => 'UserController@getMember', 'middleware' => 'auth']);
$router->post('/update-member', ['uses' => 'UserController@updateMember', 'middleware' => 'auth']);
$router->post('/delete-member/{id}', ['uses' => 'UserController@deleteMember', 'middleware' => 'auth']);


$router->get('/user', ['uses' => 'UserController@index', 'middleware' => 'auth']);



$router->get('/login', 'TransactionController@login');
