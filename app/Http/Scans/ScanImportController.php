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
        $total_violations = 0;
        $total_warnings = 0;
        

        // Tallys number of pages with errors/warnings
        $violations_pages = 0;
        $warnings_pages = 0;
        foreach ($dataset as $entry) {
            $results = json_decode($entry['results'], true);
            
            $page_has_violations = false;
            $page_has_warnings = false;
            // Tally elements in violation for a specific page
            $violations_count_this_page = 0;
            $warnings_count_this_page = 0;
            foreach($results['rule_results'] as
                [
                    "elements_violation" => $elements_violation,
                    "elements_warning"=> $elements_warning
                ]
            ){       
                $total_violations += $elements_violation;
                $violations_count_this_page += $elements_violation;

                if($elements_violation > 0 && $page_has_violations == false){
                    $page_has_violations = true;
                    
                }
                
                $total_warnings += $elements_warning;
                $warnings_count_this_page += $elements_warning;
                
                if($elements_warning > 0 && $page_has_warnings == false){
                    $page_has_warnings = true;
                    
                }
            }
            if($page_has_violations) {
                $violations_pages++;
            }
            if($page_has_warnings) {
                $warnings_pages++;
            }
           
            Page::create([
                'scan_id' =>$scan->id,
                'title'   =>$entry['title'],
                'results' =>$entry['results'],
                'violation_count'=>$violations_count_this_page,
                'warning_count'=>$warnings_count_this_page
            ]);
        }

        
        $scan->violation_count = $total_violations;
        $scan->warning_count = $total_warnings;
        $scan->violation_count_pages = $violations_pages;
        $scan->warning_count_pages = $warnings_pages;

        

        $scan->save();#
        return response()->json([
            'message' => 'Import successful',
            'data' => $scan
        ]);
    }
}
