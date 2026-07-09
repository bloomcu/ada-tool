<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;

class AuthLoginTest extends TestCase
{
    private function makeUser(string $password = 'password'): User
    {
        $organization = Organization::factory()->create();

        return User::factory()->create([
            'organization_id' => $organization->id,
            'password' => Hash::make($password),
        ]);
    }

    /** @test */
    public function it_logs_in_with_valid_credentials_and_returns_a_token()
    {
        $user = $this->makeUser('secret-password');

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonStructure(['data' => ['access_token', 'organization' => ['id', 'slug']]]);

        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /** @test */
    public function it_revokes_existing_tokens_on_login()
    {
        $user = $this->makeUser('secret-password');
        $user->createToken('old_one');
        $user->createToken('old_two');
        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertOk();

        // The two old tokens are deleted and exactly one fresh token remains.
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /** @test */
    public function it_rejects_invalid_credentials()
    {
        $user = $this->makeUser('secret-password');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.credentials.0', 'Credentials do not match.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function it_validates_email_and_password_are_present()
    {
        $this->postJson('/api/auth/login', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
