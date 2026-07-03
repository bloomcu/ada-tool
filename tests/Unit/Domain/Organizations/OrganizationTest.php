<?php

namespace Tests\Unit\Domain\Organizations;

use Tests\TestCase;

// Models
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Scan;

class OrganizationTest extends TestCase
{
    /** @test */
    public function it_has_a_slug()
    {
        $organization = Organization::factory()->create();

        $this->assertNotNull($organization->slug);
    }

    /** @test */
    public function it_uses_the_slug_for_the_route_key_name()
    {
        $organization = Organization::factory()->create();

        $this->assertEquals('slug', $organization->getRouteKeyName());
    }

    /** @test */
    public function it_has_many_users()
    {
        $organization = Organization::factory()
            ->has(User::factory())
            ->create();

        $this->assertInstanceOf(User::class, $organization->users->first());
    }

    /** @test */
    public function it_has_many_sites()
    {
        $organization = Organization::factory()
            ->has(Site::factory())
            ->create();

        $this->assertInstanceOf(Site::class, $organization->sites->first());
    }

    /** @test */
    public function it_has_many_scans()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->for($organization)->create();
        Scan::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
        ]);

        $this->assertInstanceOf(Scan::class, $organization->scans->first());
    }
}
