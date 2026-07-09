<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;

class AuthMeAndLogoutTest extends TestCase
{
    private function user(): User
    {
        return User::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);
    }

    /** @test */
    public function me_returns_the_authenticated_user()
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    /** @test */
    public function me_requires_authentication()
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    /** @test */
    public function logout_revokes_all_of_the_users_tokens()
    {
        $user = $this->user();
        $user->createToken('a');
        $user->createToken('b');
        $this->assertDatabaseCount('personal_access_tokens', 2);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Tokens Revoked');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function logout_requires_authentication()
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
