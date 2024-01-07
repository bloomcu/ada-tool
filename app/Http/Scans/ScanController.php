<?php

namespace DDD\Http\Scans;

use Illuminate\Http\Request;
use DDD\Domain\Scans\Resources\ScanResource;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Organizations\Organization;
use DDD\App\Services\Url\UrlService;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;

class ScanController extends Controller
{
    public function index(Organization $organization)
    {
        $scans = $organization->scans()->latest()->get();

        return ScanResource::collection($scans);
    }

    public function store(Organization $organization, Request $request, UrlService $urlService, ApifyInterface $apifyService)
    {
        $domain = $urlService->getDomain($request->domain);
        
        $actor = $apifyService->runActor('https://' . $domain);

        $site = $organization->sites()->firstOrCreate(['domain' => $domain]);

        $scan = $organization->scans()->create([
            'site_id'    => $site->id,
            'run_id'     => $actor['run_id'],
            'queue_id'   => $actor['queue_id'],
            'dataset_id' => $actor['dataset_id'],
        ]);

        return new ScanResource($scan);
    }

    public function show(Organization $organization, Scan $scan)
    {
        return new ScanResource($scan);
    }
}
