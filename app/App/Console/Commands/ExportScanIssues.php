<?php

namespace DDD\App\Console\Commands;

use DDD\Domain\Scans\Scan;
use DDD\Domain\Scans\ScanIssuesExport;
use Illuminate\Console\Command;

class ExportScanIssues extends Command
{
    /**
     * @var string
     */
    protected $signature = 'scans:export-issues
        {scan : The scan id to export}
        {--path= : Write the JSON to this file instead of stdout}
        {--pretty : Pretty-print the JSON}';

    /**
     * @var string
     */
    protected $description = "Export a scan's violations and warnings (trimmed, uncapped) as JSON for reporting.";

    public function handle(ScanIssuesExport $export): int
    {
        $scan = Scan::find($this->argument('scan'));

        if (! $scan) {
            $this->error("No scan found with id [{$this->argument('scan')}].");

            return self::FAILURE;
        }

        $data = $export->export($scan);

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }
        $json = json_encode($data, $flags);

        if ($path = $this->option('path')) {
            file_put_contents($path, $json);
            $this->info(sprintf(
                'Exported scan %d (%d of %d pages have issues) to %s',
                $scan->id,
                $data['scan']['pages_with_issues'],
                $data['scan']['pages_total'],
                $path,
            ));

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
