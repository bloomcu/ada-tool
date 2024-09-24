<?php

namespace DDD\Domain\Scans\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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
                    ->select('id', 'title', 'violation_count', 'warning_count',
                    'rescan_id')
                    ->with('rescan')
                    ->orderBy('violation_count', 'DESC')
                    ->orderBy('warning_count', 'DESC')
                    ->get();
            } ), 
            'created_at' => $this->created_at,
        ];
    }
}
