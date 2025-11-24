<?php

namespace DDD\App\Services\Scans;

use Carbon\Carbon;

class ScanScheduleService
{
    public function calculateNextRun(?string $frequency, ?Carbon $reference = null): ?Carbon
    {
        $date = $reference ? $reference->copy() : Carbon::now();

        return match ($frequency) {
            'quarterly' => $date->firstOfQuarter()->addQuarter()->startOfDay(),
            default => null,
        };
    }
}
