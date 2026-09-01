<?php

namespace DDD\Domain\Scans\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ScanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // ini_set('MEMORY_LIMIT', '256M');
        return [
            'id' => $this->id,
            'site' => $this->site,
            'run_id' => $this->run_id,
            'queue_id' => $this->queue_id,
            'dataset_id' => $this->dataset_id,
            'violation_count'=>$this->violation_count,
            'warning_count'=>$this->warning_count,
            'violation_count_pages'=>$this->violation_count_pages,
            'warning_count_pages'=>$this->warning_count_pages,
            'status' => $this->status,
            'page_count'=>$this->whenCounted('pages'),
            'pages' => $this->whenLoaded('pages', function() {
                return $this->pages()
                    // Add RAW query to pull only eval URL param from scan
                    ->select('id', 'title', 'violation_count', 'warning_count', 'customer_reviewable', 'rescan_id')
                    ->with('rescan')
                    // "Review this first" pages float to the top; then worst-first by counts.
                    // (MySQL sorts NULL last on DESC, so not-yet-backfilled pages sink.)
                    ->orderBy('customer_reviewable', 'DESC')
                    ->orderBy('violation_count', 'DESC')
                    ->orderBy('warning_count', 'DESC')
                    ->get();
            } ), 
            'created_at' => $this->created_at,
        ];
    }
}
