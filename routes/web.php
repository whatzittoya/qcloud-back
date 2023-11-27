<?php

/** @var \Laravel\Lumen\Routing\Router $router */

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

$router->get('/', function () use ($router) {
    return $router->app->version();
});

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



});

$router->post('/login-back', 'UserController@authenticate');
$router->post('/logout-back', ['uses'=>'UserController@logout', 'middleware'=>'auth']);
$router->post('/reset-pass', ['uses'=>'UserController@resetPass', 'middleware'=>'auth']);
$router->post('/add-member', 'UserController@addMember');
$router->get('/get-member', 'UserController@getMember');
$router->post('/update-member', 'UserController@updateMember');
$router->post('/delete-member/{id}', 'UserController@deleteMember');


$router->get('/user', 'UserController@index');



$router->get('/login', 'TransactionController@login');
