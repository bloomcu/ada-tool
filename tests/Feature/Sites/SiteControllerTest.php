<?php

namespace Tests\Feature\Sites;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Sites\Site;

class SiteControllerTest extends TestCase
{
    private function actingUser(Organization $organization): void
    {
        Sanctum::actingAs(User::factory()->create(['organization_id' => $organization->id]));
    }

    /** @test */
    public function index_lists_only_the_organizations_sites_with_a_scan_count()
    {
        $organization = Organization::factory()->create();
        Site::factory()->count(2)->create(['organization_id' => $organization->id]);
        Site::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingUser($organization);

        $this->getJson("/api/{$organization->slug}/sites")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'domain', 'scan_count']]]);
    }

    /** @test */
    public function index_requires_authentication()
    {
        $organization = Organization::factory()->create();

        $this->getJson("/api/{$organization->slug}/sites")->assertUnauthorized();
    }

    /** @test */
    public function store_creates_a_site_and_schedules_the_next_quarterly_scan()
    {
        $organization = Organization::factory()->create();
        $this->actingUser($organization);

        $response = $this->postJson("/api/{$organization->slug}/sites", [
            'title' => 'Acme',
            'domain' => 'acme-cu.com',
            'scan_schedule' => 'quarterly',
            'include_3pi' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.domain', 'acme-cu.com')
            ->assertJsonPath('data.scan_schedule', 'quarterly')
            ->assertJsonPath('data.include_3pi', false);

        $this->assertDatabaseHas('sites', [
            'organization_id' => $organization->id,
            'domain' => 'acme-cu.com',
            'scan_schedule' => 'quarterly',
            'include_3pi' => false,
        ]);

        // 'quarterly' schedules a concrete next_scan_at; 'manual' would be null.
        $this->assertNotNull(Site::where('domain', 'acme-cu.com')->first()->next_scan_at);
    }

    /** @test */
    public function store_requires_a_domain()
    {
        $organization = Organization::factory()->create();
        $this->actingUser($organization);

        $this->postJson("/api/{$organization->slug}/sites", ['title' => 'No domain'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');
    }

    /** @test */
    public function store_rejects_a_duplicate_domain()
    {
        $organization = Organization::factory()->create();
        Site::factory()->create(['organization_id' => $organization->id, 'domain' => 'taken.com']);
        $this->actingUser($organization);

        $this->postJson("/api/{$organization->slug}/sites", ['domain' => 'taken.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');
    }

    /** @test */
    public function store_rejects_an_invalid_scan_schedule()
    {
        $organization = Organization::factory()->create();
        $this->actingUser($organization);

        $this->postJson("/api/{$organization->slug}/sites", [
            'domain' => 'weekly.com',
            'scan_schedule' => 'weekly',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scan_schedule');
    }

    /** @test */
    public function store_requires_authentication()
    {
        $organization = Organization::factory()->create();

        $this->postJson("/api/{$organization->slug}/sites", ['domain' => 'x.com'])
            ->assertUnauthorized();
    }

    /** @test */
    public function show_returns_the_site()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $this->actingUser($organization);

        $this->getJson("/api/{$organization->slug}/sites/{$site->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $site->id)
            ->assertJsonPath('data.domain', $site->domain);
    }

    /** @test */
    public function update_modifies_the_site()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id, 'title' => 'Old']);
        $this->actingUser($organization);

        $this->putJson("/api/{$organization->slug}/sites/{$site->id}", ['title' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New');

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'title' => 'New']);
    }

    /** @test */
    public function update_recalculates_next_scan_when_the_schedule_changes()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create([
            'organization_id' => $organization->id,
            'scan_schedule' => 'manual',
            'next_scan_at' => null,
        ]);
        $this->actingUser($organization);

        $this->putJson("/api/{$organization->slug}/sites/{$site->id}", ['scan_schedule' => 'quarterly'])
            ->assertOk();

        $this->assertNotNull($site->refresh()->next_scan_at);
    }

    /** @test */
    public function destroy_deletes_the_site()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $this->actingUser($organization);

        $this->deleteJson("/api/{$organization->slug}/sites/{$site->id}")->assertOk();

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    /** @test */
    public function update_requires_authentication()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id, 'title' => 'Untouched']);

        // No acting user.
        $this->putJson("/api/{$organization->slug}/sites/{$site->id}", ['title' => 'Hacked'])
            ->assertUnauthorized();

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'title' => 'Untouched']);
    }

    /** @test */
    public function destroy_requires_authentication()
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);

        $this->deleteJson("/api/{$organization->slug}/sites/{$site->id}")
            ->assertUnauthorized();

        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }
}
