<?php

namespace DDD\Domain\Base\Evaluations;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;
    protected $fillable = [
        'site_id',
        'run_id',
        'queue_id',
        'dataset_id'
    ];
    public function site() {
        return $this->belongsTo(\DDD\Domain\Base\Sites\Site::class);
    }
    public function pages() {
        return $this->hasMany(\DDD\Domain\Base\Pages\Page::class);
    }

    public function getResultCounts() {
        
    }
}
