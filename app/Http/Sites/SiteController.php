<?php

namespace DDD\Http\Sites;

use DDD\Domain\Sites\Site;
use DDD\Domain\Sites\Resources\SiteResource;
use DDD\Domain\Sites\Requests\SiteUpdateRequest;
use DDD\Domain\Sites\Requests\SiteStoreRequest;
use DDD\Domain\Organizations\Organization;
use DDD\App\Helpers\UrlHelpers;
use DDD\App\Controllers\Controller;

class SiteController extends Controller
{
    public function index(Organization $organization)
    {   
        
        return SiteResource::collection($organization->sites->loadCount('scans'));
    }

    public function store(Organization $organization, SiteStoreRequest $request)
    {
        $site = $organization->sites()->create([
            'title' => $request->title,
            'url' => $request->url,
            'domain' => $request->domain, // TODO: Do we need this? If so, make into trait and cast so all url parts are updated
            'scheme' => UrlHelpers::getScheme($request->domain), // TODO: Do we need this?
            'launch_info' => $request->launch_info,
        ]);

        return new SiteResource($site);
    }

    public function show(Organization $organization, Site $site)
    {
        return new SiteResource($site->load(['scans'=>function($q){
            $q->withCount('pages');
        }]));
    }

    public function update(Organization $organization, Site $site, SiteUpdateRequest $request)
    {
        $site->update($request->validated());

        return new SiteResource($site);
    }

    public function destroy(Organization $organization, Site $site)
    {
        $site->delete();

        return new SiteResource($site);
    }
}
