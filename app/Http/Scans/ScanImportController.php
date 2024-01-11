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
        $pass = 0;
        $violations = 0;
        $warn = 0;
        $mc = 0;
        $hide = 0;
        foreach ($dataset as $entry) {
            $results = json_decode($entry['results'], true);
            
            foreach($results['rule_results'] as [
                "elements_passed" => $elements_passed,
                "elements_violation" => $elements_violation,
                "elements_warning"=> $elements_warning,
                "elements_failure"=> $elements_failure,
                "elements_manual_check"=>$elements_manual_check,
                "elements_hidden"=> $elements_hidden]
            ){
                $pass+=$elements_passed;
                $violations+=$elements_violation;
                $warn+=$elements_warning;
                $mc+=$elements_manual_check;
                $hide+=$elements_hidden;
            }
            // Page::create([
            //     'scan_id' =>$scan->id,
            //     'title'   =>$entry['title'],
            //     'results' =>$entry['results'],
            // ]);
        }
        $scan->pass_count = $pass;
        $scan->fail_count = $violations;
        $scan->warning_count = $warn;
        $scan->hidden_count = $hide;
        $scan->manual_count = $mc;
        $scan->save();
        return response()->json([
            'message' => 'Import successful',
            'data' => $scan
        ]);
    }
}
