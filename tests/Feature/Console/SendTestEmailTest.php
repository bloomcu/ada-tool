<?php

namespace Tests\Feature\Console;

use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use DDD\App\Mail\TestEmail;

class SendTestEmailTest extends TestCase
{
    /** @test */
    public function it_sends_a_test_email_to_the_given_address()
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => 'ops@example.com'])
            ->assertExitCode(0);

        Mail::assertSent(TestEmail::class, fn (TestEmail $mail) => $mail->hasTo('ops@example.com'));
    }

    /** @test */
    public function it_rejects_an_invalid_address_and_sends_nothing()
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => 'not-an-email'])
            ->assertExitCode(1);

        Mail::assertNothingSent();
    }
}
