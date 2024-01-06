<?php

namespace DDD\Domain\Organizations;

use DDD\Domain\Sites\Site;
use DDD\Domain\Base\Organizations\Organization as BaseOrganization;

class Organization extends BaseOrganization
{
    /**
     * Sites associated with this organization.
     *
     * @return hasMany
     */
    public function sites()
    {
        return $this->hasMany(Site::class);
    }
}
