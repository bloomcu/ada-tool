<?php

namespace DDD\Http\Scans;

use Illuminate\Http\Request;
use DDD\Domain\Base\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;
use DDD\Domain\Base\Evaluations\Evaluation;

class DataSetController extends Controller
{
    public function dataset(Request $request, ApifyInterface $service, Evaluation $evaluation)
    {
        
        $actor = $service->getDataset($evaluation->dataset_id);
        return response()->json([
            'message' => 'Results:',
            // 'data' => $scan
            'data' => $actor
        ]);
    }
}
