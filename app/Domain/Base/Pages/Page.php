<?php

namespace DDD\Domain\Base\Pages;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Page extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'results',
        'evaluation_id'
    ];
    
    public function evaluation () {
        return $this->belongsTo(\DDD\Domain\Base\Evaluations\Evaluation::class);
    }

    protected function results(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => json_decode($value, true),
        );
    }
}
