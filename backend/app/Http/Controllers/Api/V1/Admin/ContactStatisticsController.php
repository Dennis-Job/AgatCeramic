<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ContactStatisticsRequest;
use App\Models\ContactRequest;
use App\Queries\ContactStatisticsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ContactStatisticsController extends Controller
{
    public function __invoke(ContactStatisticsRequest $request, ContactStatisticsQuery $statistics): JsonResponse
    {
        Gate::authorize('viewAny', ContactRequest::class);

        return response()->json(['data' => $statistics->forMonth($request->string('month')->toString())]);
    }
}
