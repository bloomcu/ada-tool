<?php

use Illuminate\Support\Facades\Route;
use DDD\Domain\Base\Pages\Page;
use Illuminate\Support\Facades\Storage;
use DDD\Domain\Base\Evaluations\Evaluation;
use DDD\Domain\Base\Sites\Site;

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
// $evaluation = Evaluation::create(
//     [
//         // $table->id();
//         // $table->foreignId('site_id');
//         // $table->string('run_id');
//         // $table->string('queue_id');
//         // $table->string('results_id');
//         'site_id'=>1,
//         'run_id'=>'xxx',
//         'queue_id'=>'xxx',
//         'results_id'=>'xxx'
//     ]
// );
// $eval_id = $evaluation->id;
// $data = json_decode($json = Storage::disk('private')->get('sample-eval.json'), true);
//         // print_r($data);
//         foreach ($data as $entry) {
//             $page = Page::create(
//                 [
//                     'title'=>$entry['title'],
//                     'results'=>$entry['results'],
//                     'evaluation_id'=>$eval_id
//                 ]
//             );
//         }
Route::get('/', function () {
    $site = Site::find(1)->with('evaluations.pages')->get();
    // print_r($site);
    return view('welcome', compact('site'));
});
