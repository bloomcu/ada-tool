<?php

namespace DDD\App\Services\Apify;

use Illuminate\Support\Facades\Http;
use DDD\App\Services\Apify\ApifyInterface;
use Illuminate\Support\Facades\Log;

class ApifyADAScanner implements ApifyInterface
{
    public function __construct(
        protected string $token,
        protected string $actor,
    ) {}

    public function runActor(string $url)
    {
        try {
            $request = Http::post('https://api.apify.com/v2/acts/' . $this->actor . '/runs?token=' . $this->token, [
                'startUrls' => [['url' => $url . '/']],
                'pseudoUrls' => [['purl' => $url . '/[.*?]']],
                'useResidentialProxy' => true
            ])->json();

            $response = $request['data'];

            return [
                'run_id'   => $response['id'],
                'queue_id'   => $response['defaultRequestQueueId'],
                'dataset_id' => $response['defaultDatasetId'],
            ];
        } catch (\Exception $exception) {
            abort(500, 'Could not start Apify actor. ' . $request['error']['message']);
        }
    }

    public function getStatus(string $runId, string $queueId)
    {
        try {
            $run = Http::get('https://api.apify.com/v2/actor-runs/' . $runId . '?token=' . $this->token)->json();
            $queue = Http::get('https://api.apify.com/v2/request-queues/' . $queueId . '?token=' . $this->token)->json();

            return [
                'status'  => $run['data']['status'],
                'total'   => $queue['data']['totalRequestCount'],
                'handled' => $queue['data']['handledRequestCount'],
                'pending' => $queue['data']['pendingRequestCount'],
            ];
        } catch (\Exception $exception) {
            abort(500, 'Could not retrieve status from Apify queue. ' . $run['error']['message']);
        }
    }

    public function getDataset(string $datasetId, $per_page = 0, $page_number = 0)
    {
        try {
            $query_string = '';
            if($per_page) {
                $query_string.='&limit='.$per_page;
            }
            if($page_number && $page_number-1) {
                $query_string.='&offset=' . ($page_number - 1)*$per_page;
            }
            
            
            $request = Http::timeout(100)->get('https://api.apify.com/v2/datasets/' . $datasetId . '/items?token=' . $this->token.$query_string)->json();

            // Only return results with a url (this omits PDFs and other files that slipped into the crawl,
            // modified to explicitly ignore images and PFD withs preg_match)
            $collection = collect($request);
            $filtered = $collection->filter(function ($item) {
                return isset($item['url']) && ! preg_match('/\.(pdf|jpg|jpeg|png|gif|bmp|webp)$/i', $item['url']);
            });

            $mapped = $filtered->map(function ($item) {
                return [
                    'title'         => $item['title'] ?? null,
                    'url'           => $item['url'] ?? null,
                    'results'       => $item['results'] ?? null,
                ];
            });

            return $mapped;
        } catch (\Exception $exception) {
            abort(500, 'Could not get Apify results. ' . $exception);
        }
    }
    public function getDatasetMeta($datasetId) {
        try {
           
            $request = Http::timeout(100)->get('https://api.apify.com/v2/datasets/' . $datasetId . '?token=' . $this->token)->json();
            $response = $request['data'];
            return $response;

            // Only return results with a url (this omits PDFs and other files that slipped into the crawl)
            // $collection = collect($request);
            // $filtered = $collection->filter(function ($item) {
            //     return isset($item['url']);
            // });

            // $mapped = $filtered->map(function ($item) {
            //     return [
            //         'title'         => $item['title'] ?? null,
            //         'url'           => $item['url'] ?? null,
            //         'results'       => $item['results'] ?? null,
            //     ];
            // });

            // return $mapped;
        } catch (\Exception $exception) {
            abort(500, 'Could not get Apify results. ' . $exception);
        }
    }

    public function abortRun(string $runId)
    {
        try {
            $request = Http::post('https://api.apify.com/v2/actor-runs/' . $runId . '/abort?token=' . $this->token)->json();
            return $request;
        } catch (\Exception $exception) {
            abort(500, 'Could not abort Apify run. ' . $exception);
        }
    }
}
