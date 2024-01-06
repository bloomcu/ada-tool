<?php

namespace DDD\Domain\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use DDD\Domain\Sites\Site;
use DDD\Domain\Evaluations\Evaluation;

class Page extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
    
    public function evaluation () {
        return $this->belongsTo(Evaluation::class);
    }

    protected function results(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => json_decode($value, true),
        );
    }
}
