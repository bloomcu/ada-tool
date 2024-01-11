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
        // These numbers include all elements and all tests these are not unique and may be useful in tracking global issues 
        $passes = 0; 
        $violations = 0;
        $failures = 0;
        $warnings = 0;
        $must_checks = 0;
        $hidden = 0;

        // Tallys number of pages with errors/warnings
        $violations_pages = 0;
        $warnings_pages = 0;
        foreach ($dataset as $entry) {
            $results = json_decode($entry['results'], true);
            // return $results;
            $violations_page = false;
            $warnings_page = false;
            foreach($results['rule_results'] as
                [
                    "elements_violation" => $elements_violation,
                    "elements_warning"=> $elements_warning
                ]
            ){
                
                $violations += $elements_violation;
                if($elements_violation > 0 && $violations_page == false){
                    $violations_page = true;
                }
                
                $warnings += $elements_warning;
                if($elements_warning > 0 && $warnings_page == false){
                    $warnings_page = true;
                }
            }
            if($violations_page) {
                $violations_pages++;
            }
            if($warnings_page) {
                $warnings_pages++;
            }
           
            Page::create([
                'scan_id' =>$scan->id,
                'title'   =>$entry['title'],
                'results' =>$entry['results'],
            ]);
        }

        
        $scan->violation_count = $violations;
        $scan->warning_count = $warnings;
        $scan->violation_count_pages = $violations_pages;
        $scan->warning_count_pages = $warnings_pages;

        

        $scan->save();#
        return response()->json([
            'message' => 'Import successful',
            'data' => $scan
        ]);
    }
}
