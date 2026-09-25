<?php

namespace DDD\App\Console\Commands;

use DDD\App\Mail\TestEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mail:test {email : Address to send the test email to}';

    /**
     * @var string
     */
    protected $description = 'Send a plain test email via the configured mailer — for validating mail credentials (e.g. a Mailgun API key change) without running a scan.';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Not a valid email address: {$email}");

            return self::FAILURE;
        }

        Mail::to($email)->send(new TestEmail());

        $this->info("Test email sent to {$email} via mailer [" . config('mail.default') . '].');

        return self::SUCCESS;
    }
}
