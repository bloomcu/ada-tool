<?php

use Illuminate\Support\Facades\Route;
use DDD\Domain\Pages\Page;
use Illuminate\Support\Facades\Storage;
use DDD\Domain\Evaluations\Evaluation;
use DDD\Domain\Sites\Site;

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
    $evaluations = Evaluation::all();
    foreach($evaluations as $evaluation) {
        echo $evaluation->id . '<br>';
    }
    $site = Site::find(1)->with('evaluations.pages')->get();
    // print_r($site);
    return view('welcome', compact('site'));
});
