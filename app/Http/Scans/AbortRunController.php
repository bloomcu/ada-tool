<?php

namespace DDD\Http\Scans;

use Illuminate\Http\Request;
use DDD\Domain\Base\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;
use DDD\Domain\Base\Evaluations\Evaluation;

class AbortRunController extends Controller
{
    public function abortRun(Request $request, ApifyInterface $service, Evaluation $evaluation)
    {
        $actor = $service->abortRun($evaluation->run_id);

        // $scan = $organization->scans()->create([
        //     'url'        => $request->url,
        //     'run_id'     => $actor['run_id'],
        //     'queue_id'   => $actor['queue_id'],
        //     'dataset_id' => $actor['dataset_id'],
        // ]);

        return response()->json([
            'message' => 'Results:',
            // 'data' => $scan
            'data' => $actor
        ]);
    }
}
