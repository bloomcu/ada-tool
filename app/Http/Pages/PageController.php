<?php

namespace DDD\Http\Pages;

use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;
use DDD\Domain\Pages\CustomerEditableRules;
use DDD\Domain\Organizations\Organization;
use DDD\App\Controllers\Controller;

class PageController extends Controller
{
    public function show(Organization $organization, Scan $scan, Page $page)
    {
        $data = $page->toArray();

        // Flag CMS-editable issues per rule and float them to the top (derived on read).
        if (is_array($data['results'] ?? null)) {
            $data['results'] = CustomerEditableRules::annotate($data['results']);
        }

        return response()->json([
            'data' => $data,
        ]);
    }
}
