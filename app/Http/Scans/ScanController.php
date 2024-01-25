<?php

namespace DDD\Http\Scans;

use DDD\Domain\Scans\Resources\ScanResource;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Organizations\Organization;
use DDD\App\Controllers\Controller;

class ScanController extends Controller
{
    public function index(Organization $organization)
    {
        $scans = $organization->scans()->latest()->get();

        return ScanResource::collection($scans->loadCount('pages'));
    }

    public function show(Organization $organization, Scan $scan)
    {
        
        return new ScanResource($scan->load('pages'));
    }
}
