<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;

class AuthRegisterTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Password::uncompromised() calls the HaveIBeenPwned range API; an empty
        // body means "not found in any breach" so a strong password passes.
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Admin',
            'email' => 'jane@example.com',
            'organization_title' => 'Jane CU',
            'password' => 'Str0ng-P@ssw0rd!',
            'password_confirmation' => 'Str0ng-P@ssw0rd!',
        ], $overrides);
    }

    /** @test */
    public function it_registers_an_organization_and_user_and_returns_a_token()
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertOk()
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonStructure(['data' => ['access_token', 'organization' => ['id', 'slug']]]);

        $this->assertDatabaseHas('organizations', ['title' => 'Jane CU']);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'role' => 'admin']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /** @test */
    public function it_rejects_a_duplicate_email()
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $this->postJson('/api/auth/register', $this->validPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /** @test */
    public function it_rejects_a_weak_password()
    {
        $this->postJson('/api/auth/register', $this->validPayload([
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    /** @test */
    public function it_requires_name_email_organization_and_password()
    {
        $this->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'organization_title', 'password']);
    }
}
