<?php

namespace DDD\Domain\Base\Files;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Traits
use DDD\App\Traits\BelongsToOrganization;
use DDD\App\Traits\BelongsToUser;

class File extends Model
{
    use BelongsToOrganization,
        BelongsToUser;

    protected $guarded = ['id'];

    public function getStorageUrl() {
        return config('cdn.cdn_url') . '/' . $this->path;
    }
}
