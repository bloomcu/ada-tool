<?php

namespace DDD\Http\Scans;

use Illuminate\Http\Request;
use DDD\Domain\Base\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;
use DDD\Domain\Base\Evaluations\Evaluation;

class StatusController extends Controller
{
    public function status(Request $request, ApifyInterface $service, Evaluation $evaluation)
    {
        $actor = $service->getStatus($evaluation->run_id, $evaluation->queue_id);
        $evaluation->status = $actor['status'];
        if($evaluation->save()){
            //TODO: if status matches any "done" status, flag it on the model and return
            return response()->json([
                'message' => 'Status:',
                // 'data' => $scan
                'data' => $actor
            ]);
        }
        
    }
}
