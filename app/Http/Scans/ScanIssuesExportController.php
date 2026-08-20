<?php

namespace DDD\Http\Scans;

use DDD\App\Controllers\Controller;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Scans\ScanIssuesExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScanIssuesExportController extends Controller
{
    /**
     * Download a scan's violations/warnings (trimmed, uncapped) as a JSON file.
     *
     * Route-model binding + scopeBindings() tie {scan} to {organization}, so a scan
     * from another org 404s. Auth is enforced by the `auth:sanctum` group in routes.
     */
    public function download(Organization $organization, Scan $scan, ScanIssuesExport $export): StreamedResponse
    {
        $data = $export->export($scan);

        $json = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
        );

        $filename = "scan-{$scan->id}-issues.json";

        return response()->streamDownload(
            fn () => print($json),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
