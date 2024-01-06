<?php

namespace DDD\Domain\Evaluations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use DDD\Domain\Sites\Site;
use DDD\Domain\Pages\Page;

class Evaluation extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'site_id',
        'run_id',
        'queue_id',
        'results_id'
    ];

    public function site() {
        return $this->belongsTo(Site::class);
    }

    public function pages() {
        return $this->hasMany(Page::class);
    }

    public function getResultCounts() {
        
    }
}
