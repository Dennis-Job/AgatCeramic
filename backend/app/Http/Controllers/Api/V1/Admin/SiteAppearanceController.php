<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSiteAppearanceRequest;
use App\Http\Resources\HomePageResource;
use App\Models\HomePage;
use App\Queries\SiteAppearanceQuery;
use App\Services\SiteAppearanceManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SiteAppearanceController extends Controller
{
    public function show(SiteAppearanceQuery $query): HomePageResource
    {
        Gate::authorize('viewAny', HomePage::class);

        return new HomePageResource($query->get(true));
    }

    public function update(UpdateSiteAppearanceRequest $request, SiteAppearanceManagementService $management, SiteAppearanceQuery $query): HomePageResource
    {
        Gate::authorize('update', HomePage::class);
        $management->update($this->authenticatedAdmin($request), $request->validated());

        return new HomePageResource($query->get(true));
    }

    public function publish(Request $request, SiteAppearanceManagementService $management, SiteAppearanceQuery $query): HomePageResource
    {
        Gate::authorize('update', HomePage::class);
        $management->publish($this->authenticatedAdmin($request));

        return new HomePageResource($query->get(true));
    }
}
