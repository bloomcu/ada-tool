<?php

namespace DDD\Http\Scans;

use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;
use DDD\Domain\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;

class ScanImportController extends Controller
{
    public function import(Organization $organization, Scan $scan, ApifyInterface $apifyService)
    {
        $dataset = $apifyService->getDataset($scan->dataset_id);
        
        $dataset = json_decode($dataset, true);

        foreach ($dataset as $entry) {
            Page::create([
                'scan_id' =>$scan->id,
                'title'   =>$entry['title'],
                'results' =>$entry['results'],
            ]);
        }

        return response()->json([
            'message' => 'Import successful',
            'data' => $scan
        ]);
    }
}
