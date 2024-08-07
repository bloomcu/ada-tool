<?php

namespace DDD\Domain\Scans\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ScanSucceeded extends Mailable
{
    use Queueable, SerializesModels;
    public $scan;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($scan)
    {
        //
        $this->scan = $scan;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Scan Succeeded')->view('emails.scan_succeeded');
    }
}
