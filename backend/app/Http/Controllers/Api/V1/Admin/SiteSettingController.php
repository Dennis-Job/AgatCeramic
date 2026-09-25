<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSiteSettingRequest;
use App\Http\Resources\AdminSiteSettingResource;
use App\Models\SiteSetting;
use App\Services\SiteSettingManagementService;
use Illuminate\Support\Facades\Gate;

class SiteSettingController extends Controller
{
    public function __construct(private readonly SiteSettingManagementService $management) {}

    public function show(): AdminSiteSettingResource
    {
        Gate::authorize('viewAny', SiteSetting::class);

        return new AdminSiteSettingResource(SiteSetting::query()->findOrFail(1));
    }

    public function update(UpdateSiteSettingRequest $request): AdminSiteSettingResource
    {
        Gate::authorize('update', SiteSetting::class);

        return new AdminSiteSettingResource($this->management->update($this->authenticatedAdmin($request), $request->validated()));
    }
}
