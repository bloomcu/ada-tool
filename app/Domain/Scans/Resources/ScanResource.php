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
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
