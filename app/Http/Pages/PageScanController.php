<?php

namespace DDD\Http\Pages;

use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Resources\ScanResource;
use DDD\Domain\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;
use DDD\Domain\Pages\Page;
use DDD\Domain\Scans\Scan;
use Illuminate\Http\Request;

class PageScanController extends Controller
{
    //
    
    public function store(Organization $organization, Site $site, Scan $scan, Page $page, ApifyInterface $apifyService)
    {
        // run the actor with enqueulinks set to false to prevent additional pages being scanned
        
        // die();
        if(! $page->results['eval_url']) {
            return false;
        }
        $actor = $apifyService->runActor($page->results['eval_url'], false);

        $new_scan = $site->scans()->create([
            'organization_id' => $organization->id,
            'run_id'          => $actor['run_id'],
            'queue_id'        => $actor['queue_id'],
            'dataset_id'      => $actor['dataset_id'],
            'is_single_page' => true
        ]);

        $page->update(['rescan_id'=>$new_scan->id]);

        return new ScanResource($new_scan);
    }
}
