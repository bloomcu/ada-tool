<?php

namespace DDD\Domain\Sites\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use DDD\Domain\Scans\Resources\ScanResource;

class SiteResource extends JsonResource
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
            'organization_id' => $this->organization_id,
            'title' => $this->title,
            'domain' => $this->domain,
            'scans' => ScanResource::collection($this->scans),
        ];
    }
}
