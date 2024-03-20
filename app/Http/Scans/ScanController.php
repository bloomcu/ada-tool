<?php

namespace DDD\Http\Scans;

use DDD\Domain\Scans\Resources\ScanResource;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Organizations\Organization;
use DDD\App\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class ScanController extends Controller
{
    public function index(Organization $organization)
    {
        $scans = $organization->scans()->latest()->get();

        return ScanResource::collection($scans->loadCount('pages'));
    }

    public function show(Organization $organization, Scan $scan)
    {
        ini_set('memory_limit', '2048M');
        $resource = new ScanResource($scan->load('pages'));
        // Log::info('usage_old' . memory_get_usage(true));
        return $resource;
    }
}
