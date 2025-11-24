<?php

namespace DDD\Domain\Scans\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use DDD\Domain\Sites\Site;
use DDD\Domain\Scans\Scan;

class ScheduledScanStarted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Site $site,
        public Scan $scan,
        public string $scanUrl
    ) {}

    public function build()
    {
        $subject = sprintf(
            'ADA scan started for %s',
            $this->site->title ?: $this->site->domain
        );

        return $this->subject($subject)
            ->view('emails.scans.started')
            ->with([
                'site' => $this->site,
                'scan' => $this->scan,
                'scanUrl' => $this->scanUrl,
            ]);
    }
}
