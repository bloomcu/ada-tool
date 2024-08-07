<?php

namespace DDD\Domain\Scans\Observers;

use DDD\Domain\Scans\Mail\ScanSucceeded;
use DDD\Domain\Scans\Scan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ScanObserver
{
    /**
     * Handle the scan "created" event.
     *
     * @param  \DDD\scan  $scan
     * @return void
     */
    public function created(Scan $scan)
    {
        //
    }

    /**
     * Handle the scan "updated" event.
     *
     * @param  \DDD\scan  $scan
     * @return void
     */
    public function updated(Scan $scan)
    {
        Log::alert($scan->id.' scan updated');
        Log::alert('is dirty? '. $scan->isDirty('status'));
        Log::alert('status? '.$scan->status);
        Log::alert('original? '.$scan->getOriginal('status'));

        // Check if status changed from "running" to "succeeded"
        if ($scan->isDirty('status') && $scan->status === 'SUCCEEDED' && $scan->getOriginal('status') === 'RUNNING') {
            $scan->load('site.organization');
            Log::info($scan);
            Mail::to('bryan@bloomcu.com')->send(new ScanSucceeded($scan));
            // Mail::to($scan->site->notification_email)->send(new ScanSucceeded($scan));
        }
    }

    /**
     * Handle the scan "deleted" event.
     *
     * @param  \DDD\scan  $scan
     * @return void
     */
    public function deleted(Scan $scan)
    {
        //
    }

    /**
     * Handle the scan "restored" event.
     *
     * @param  \DDD\scan  $scan
     * @return void
     */
    public function restored(Scan $scan)
    {
        //
    }

    /**
     * Handle the scan "force deleted" event.
     *
     * @param  \DDD\scan  $scan
     * @return void
     */
    public function forceDeleted(Scan $scan)
    {
        //
    }
}
