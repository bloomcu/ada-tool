<?php

namespace DDD\Http\Scans;

use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;
use DDD\Domain\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class ScanImportController extends Controller
{
    public function import(Organization $organization, Scan $scan, ApifyInterface $apifyService)
    {
        // ini_set('memory_limit', '256M');
        $chunk_size = 20;
        $dataset_meta = $apifyService->getDataSetMeta($scan->dataset_id);
        $dataset_count = $dataset_meta['itemCount'];
        $page_count = floor($dataset_count/$chunk_size);
        
        // These numbers include all elements and all tests these are not unique and may be useful in tracking global issues 
        $total_violations = 0;
        $total_warnings = 0;
        $violations_pages = 0;
        $warnings_pages = 0;
      
        $collected_page_count = 0;
        // Fetch the data set in chunks
        for($i = 1; $i<=$page_count; $i++) {
            $dataset = $apifyService->getDataset($scan->dataset_id, 20, $i);
            // Log::info('usage_get: ' . memory_get_peak_usage(true));
            $dataset = json_decode($dataset, true);
            // Log::info('usage_decode: ' . memory_get_peak_usage(true));
            
            foreach ($dataset as $entry) {
                $pages = [];
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
                $pages[] = [
                    'scan_id' =>$scan->id,
                    'title'   =>$entry['title'],
                    'results' =>$entry['results'],
                    'violation_count'=>$violations_count_this_page,
                    'warning_count'=>$warnings_count_this_page
                ];
                // Log::info('usage_after_datasets: ' . memory_get_peak_usage(true));
                $chunks = array_chunk($pages, 1);
                foreach ($chunks as $chunk) {
                    Page::insert($chunk);
                }
                
            }
            
        }

        $scan->violation_count = $total_violations;
        $scan->warning_count = $total_warnings;
        $scan->violation_count_pages = $violations_pages;
        $scan->warning_count_pages = $warnings_pages;

        $scan->save();#
        // Log::info('usage_after_save: ' . memory_get_peak_usage(true));
        return response()->json([
            'message' => 'Import successful',
            'data' => $scan
        ]);
    }
}
