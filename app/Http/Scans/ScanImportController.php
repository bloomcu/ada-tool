<?php

namespace DDD\Http\Scans;

use DDD\Domain\Scans\Scan;
use DDD\Domain\Pages\Page;
use DDD\Domain\Organizations\Organization;
use DDD\App\Services\Apify\ApifyInterface;
use DDD\App\Controllers\Controller;
use DDD\Domain\Sites\Site;
use Illuminate\Support\Facades\Log;

class ScanImportController extends Controller
{
    public function import(Organization $organization, Scan $scan, ApifyInterface $apifyService)
    {
        
        
        $chunk_size = 20;
        $dataset_meta = $apifyService->getDataSetMeta($scan->dataset_id);
        $dataset_count = $dataset_meta['itemCount'];
        
        $page_count = ceil($dataset_count/$chunk_size);
        
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
    
    public function importPage(Organization $organization, Site $site, Scan $scan, Page $page, ApifyInterface $apifyService ) {
        
        if($page->rescan) {
            $rescan = $page->rescan;
            
            $page_has_violations = false;
            $page_has_warnings = false;

            $violations_count_this_page = 0;
            $warnings_count_this_page = 0;

            $dataset = $apifyService->getDataset($rescan->dataset_id, 20);
            $dataset = json_decode($dataset, true);
            $dataset = $dataset[0];
            
            $results = json_decode($dataset['results'], true);

            foreach($results['rule_results'] as
                    [
                        "elements_violation" => $elements_violation,
                        "elements_warning"=> $elements_warning
                    ]
                ){       
                    $violations_count_this_page += $elements_violation;
                    if($elements_violation > 0 && $page_has_violations == false){
                        $page_has_violations = true;
                        
                    }
                    

                    $warnings_count_this_page += $elements_warning;
                    
                    if($elements_warning > 0 && $page_has_warnings == false){
                        $page_has_warnings = true;
                        
                    }
                }
                // return array_keys($scan->toArray());
                // return array_keys($page->toArray());

                
                 // Adjust scan totals
                $previous_warning_count = $page->warning_count;
                $previous_violation_count = $page->violation_count;
                $scan->violation_count;
                $scan->warning_count;

                $violation_count_pages = $scan->violation_count_pages += $this->_adjust_error_count($page->violation_count, $violations_count_this_page);
                $warning_count_pages = $scan->warning_count_pages += $this->_adjust_error_count($page->warning_count, $warnings_count_this_page);
                
                $scan_updates= [
                    'violation_count'=> $scan->violation_count - $previous_violation_count + $violations_count_this_page,
                    'warning_count'=> $scan->warning_count - $previous_warning_count + $warnings_count_this_page,
                    'violation_count_pages'=> $violation_count_pages,
                    'warning_count_pages'=>$warning_count_pages
                ];
                $scan->update($scan_updates);

                $new_results = [
                    'title'   =>$dataset['title'],
                    'results' =>$dataset['results'],
                    'violation_count'=>$violations_count_this_page,
                    'warning_count'=>$warnings_count_this_page,
                    'rescan_id' => null
                ];
                $page->update($new_results);
                
           
            
                //return ssomething to update view
                return 'success';
            
            
        }
        return 'test';
        
        // $dataset = $apifyService->getDataset($scan->dataset_id, 20);
        
    }
    private function _adjust_error_count($previous, $current) {
        if($previous == 0 && $current> 0) {
            return 1;
        } else if($previous > 0 && $current == 0) {
            return -1;
        } else {
            return 0;
        }
    }
}
