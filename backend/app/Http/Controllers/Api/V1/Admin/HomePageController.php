<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateHomePageRequest;
use App\Http\Resources\HomePageResource;
use App\Models\HomePage;
use App\Queries\HomePageContentQuery;
use App\Services\HomePageManagementService;
use Illuminate\Support\Facades\Gate;

class HomePageController extends Controller
{
    public function show(HomePageContentQuery $query): HomePageResource
    {
        Gate::authorize('viewAny', HomePage::class);

        return new HomePageResource($query->get(true));
    }

    public function update(UpdateHomePageRequest $request, HomePageManagementService $management, HomePageContentQuery $query): HomePageResource
    {
        Gate::authorize('update', HomePage::class);
        $management->update($this->authenticatedAdmin($request), $request->validated());

        return new HomePageResource($query->get(true));
    }
}
