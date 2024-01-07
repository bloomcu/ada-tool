<?php

namespace DDD\Domain\Sites;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use DDD\Domain\Scans\Scan;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Evaluations\Evaluation;

class Site extends Model 
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    /**
     * Organization this model belongs to.
     *
     * @return belongsTo
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scans associated with the site.
     *
     * @return hasMany
     */
    public function scans()
    {
        return $this->hasMany(Scan::class)->latest();
    }

    /**
     * Evaluations associated with the site.
     *
     * @return hasMany
     */
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }
}
