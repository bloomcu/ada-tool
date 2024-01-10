<?php

use Illuminate\Support\Facades\Route;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Scan;

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

Route::get('/', function () {
    $scans = Scan::all();
    foreach($scans as $scan) {
        echo $scan->id . '<br>';
    }
    $site = Site::find(1)->with('scans.pages')->get();
    // print_r($site);
    return view('welcome', compact('site'));
});
