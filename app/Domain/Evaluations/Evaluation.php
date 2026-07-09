<?php

namespace DDD\Domain\Evaluations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use DDD\Domain\Sites\Site;
use DDD\Domain\Pages\Page;

class Evaluation extends Model
{
    use HasFactory;
    
    protected $guarded = [
        'id',
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
