<?php
  
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Laravel woo api library
//https://github.com/pixelpeter/laravel5-woocommerce-api-client



Route::get('/get_orders', 'ordersController@orders');
Route::get('/get_tracking', 'ordersController@tracking');

