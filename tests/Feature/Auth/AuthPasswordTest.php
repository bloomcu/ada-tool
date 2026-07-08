<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use DDD\Domain\Base\Users\User;
use DDD\Domain\Organizations\Organization;

class AuthPasswordTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Password reset validates uncompromised() -> HaveIBeenPwned range API.
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    private function user(string $email = 'user@example.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'organization_id' => Organization::factory()->create()->id,
        ]);
    }

    /** @test */
    public function forgot_returns_a_neutral_message_for_a_known_email()
    {
        $user = $this->user();

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If this is a valid account email, you will recieve a password reset email.');
    }

    /** @test */
    public function forgot_does_not_reveal_whether_an_email_exists()
    {
        // Unknown email still returns 200 with the same neutral message.
        $this->postJson('/api/auth/password/forgot', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If this is a valid account email, you will recieve a password reset email.');
    }

    /** @test */
    public function forgot_validates_the_email_format()
    {
        $this->postJson('/api/auth/password/forgot', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /** @test */
    public function reset_changes_the_password_with_a_valid_token()
    {
        $user = $this->user();
        $token = Password::createToken($user);
        $newPassword = 'Br@ndN3w-Password!';

        $this->postJson('/api/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password successfully reset.');

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
    }

    /** @test */
    public function reset_fails_with_an_invalid_token()
    {
        $user = $this->user();
        $newPassword = 'Br@ndN3w-Password!';

        $this->postJson('/api/auth/password/reset', [
            'token' => 'totally-invalid-token',
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'There was a problem resetting the password.');

        $this->assertFalse(Hash::check($newPassword, $user->fresh()->password));
    }
}
