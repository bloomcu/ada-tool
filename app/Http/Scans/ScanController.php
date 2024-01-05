<?php

namespace DDD\Http\Scans;

use Illuminate\Http\Request;
use DDD\Domain\Base\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;
use DDD\Domain\Base\Evaluations\Evaluation;

class ScanController extends Controller
{
    public function store(Request $request, ApifyInterface $service, Evaluation $evaluation)
    {
        $evaluation->site_id = 1;
        $actor = $service->runActor($request->url);

        // $scan = $organization->scans()->create([
        //     'url'        => $request->url,
        //     'run_id'     => $actor['run_id'],
        //     'queue_id'   => $actor['queue_id'],
        //     'dataset_id' => $actor['dataset_id'],
        // ]);
        $evaluation->run_id = $actor['run_id'];
        $evaluation->queue_id = $actor['queue_id'];
        $evaluation->dataset_id = $actor['dataset_id'];
        
        if($evaluation->save()) {
            return response()->json([
                'message' => 'Scan in progress',
                // 'data' => $scan
                'data' => [
                    'evaluation'    =>$evaluation,
                    'actor'         =>$actor
                ]
            ]);
        }
        return false;
    }
}
