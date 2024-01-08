<?php

namespace DDD\Http\Scans;

use Illuminate\Http\Request;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Evaluations\Evaluation;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;

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

    // Refactored version
    public function show(Organization $organization, Scan $scan, ApifyInterface $apifyService)
    {
        $actor = $apifyService->getStatus($scan->run_id, $scan->queue_id);

        $scan->update([
            'status' => $actor['status']
        ]);

        return response()->json([
            'data' => $actor['status']
        ]);
    }
}
