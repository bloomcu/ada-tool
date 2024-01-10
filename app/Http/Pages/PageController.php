<?php

namespace DDD\Http\Pages;

use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;
use DDD\Domain\Organizations\Organization;
use DDD\App\Controllers\Controller;

class PageController extends Controller
{
    public function show(Organization $organization, Scan $scan, Page $page)
    {
        // return new PageResource($page);

        return response()->json([
            'data' => $page
        ]);
    }
}
