<?php

namespace DDD\Http\Sites;

use DDD\Domain\Sites\Site;
use DDD\Domain\Sites\Resources\SiteResource;
use DDD\Domain\Sites\Requests\SiteUpdateRequest;
use DDD\Domain\Sites\Requests\SiteStoreRequest;
use DDD\Domain\Organizations\Organization;
use DDD\App\Helpers\UrlHelpers;
use DDD\App\Controllers\Controller;
use DDD\App\Services\Scans\ScanScheduleService;

class SiteController extends Controller
{
    public function index(Organization $organization)
    {   
        
        return SiteResource::collection($organization->sites->loadCount('scans'));
    }

    public function store(Organization $organization, SiteStoreRequest $request, ScanScheduleService $scheduleService)
    {
        $scanSchedule = $request->input('scan_schedule', 'manual');

        $notificationEmails = $request->input('scan_notification_emails');

        $site = $organization->sites()->create([
            'title' => $request->title,
            'url' => $request->url,
            'domain' => $request->domain, // TODO: Do we need this? If so, make into trait and cast so all url parts are updated
            'scheme' => UrlHelpers::getScheme($request->domain), // TODO: Do we need this?
            'launch_info' => $request->launch_info,
            'scan_schedule' => $scanSchedule,
            'next_scan_at' => $scheduleService->calculateNextRun($scanSchedule),
            'scan_notification_emails' => $notificationEmails ? trim($notificationEmails) : null,
        ]);

        return new SiteResource($site);
    }

    public function show(Organization $organization, Site $site)
    {
        return new SiteResource($site->load(['scans'=>function($q){
            $q->where('is_single_page', '!=', true);
            $q->withCount('pages');
        }]));
    }

    public function update(Organization $organization, Site $site, SiteUpdateRequest $request, ScanScheduleService $scheduleService)
    {
        $data = $request->validated();

        if (array_key_exists('scan_notification_emails', $data)) {
            $data['scan_notification_emails'] = $data['scan_notification_emails']
                ? trim($data['scan_notification_emails'])
                : null;
        }

        if (array_key_exists('scan_schedule', $data) && $data['scan_schedule'] !== $site->scan_schedule) {
            $data['next_scan_at'] = $scheduleService->calculateNextRun($data['scan_schedule']);
        }

        $site->update($data);

        return new SiteResource($site->refresh());
    }

    public function destroy(Organization $organization, Site $site)
    {
        $site->delete();

        return new SiteResource($site);
    }
}
