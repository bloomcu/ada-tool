<?php

namespace DDD\App\Mail;

use Illuminate\Mail\Mailable;

/**
 * Minimal "it works" email for validating mail credentials (e.g. a Mailgun API key
 * change) via `php artisan mail:test`. No domain data, no scan — just proof the mailer sends.
 */
class TestEmail extends Mailable
{
    public function build(): self
    {
        return $this->subject('Test email')
            ->html('This is a test email from ' . config('app.name') . '.');
    }
}
