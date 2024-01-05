<?php

namespace DDD\Http\Scans;

use Illuminate\Http\Request;
use DDD\Domain\Base\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;

class StatusController extends Controller
{
    public function status(Request $request, ApifyInterface $service)
    {
        $actor = $service->getStatus($request->run_id, $request->queue_id);

        // $scan = $organization->scans()->create([
        //     'url'        => $request->url,
        //     'run_id'     => $actor['run_id'],
        //     'queue_id'   => $actor['queue_id'],
        //     'dataset_id' => $actor['dataset_id'],
        // ]);

        return response()->json([
            'message' => 'Status:',
            // 'data' => $scan
            'data' => $actor
        ]);
    }
}
