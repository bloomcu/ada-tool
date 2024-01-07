<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DDD\Domain\Pages\Page;
use Illuminate\Support\Facades\Storage;
use DDD\Domain\Evaluations\Evaluation;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = json_decode($json = Storage::disk('private')->get('sample-eval.json'), true);
        // print_r($data);
        $evaluation = Evaluation::create(
            [
                'site_id'=>1,
                'run_id'=>'xxx',
                'queue_id'=>'xxx',
                'dataset_id'=>'xxx'
            ]
        );
        $eval_id = $evaluation->id;

        // print_r($data);
        foreach ($data as $entry) {
            $page = Page::create(
                [
                    'title'=>$entry['title'],
                    'results'=>$entry['results'],
                    'evaluation_id'=>$eval_id
                ]
            );
        }
       
        
    }
}
