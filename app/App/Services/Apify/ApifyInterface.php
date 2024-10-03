<?php

namespace DDD\App\Services\Apify;

interface ApifyInterface
{
    public function runActor(string $url, bool $enqueueLinks);
    public function getStatus(string $runId, string $queueId);
    public function getDataset(string $datasetId);
    public function abortRun(string $runId);
}
