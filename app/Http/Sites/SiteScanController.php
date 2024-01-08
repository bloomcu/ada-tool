<?php

namespace DDD\Http\Sites;

use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Resources\ScanResource;
use DDD\Domain\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;

class SiteScanController extends Controller
{
    public function store(Organization $organization, Site $site, ApifyInterface $apifyService)
    {
        $actor = $apifyService->runActor('https://' . $site->domain);

        $scan = $site->scans()->create([
            'organization_id' => $organization->id,
            'run_id'          => $actor['run_id'],
            'queue_id'        => $actor['queue_id'],
            'dataset_id'      => $actor['dataset_id'],
        ]);

        return new ScanResource($scan);
    }
}
