<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\OrderStatisticsRequest;
use App\Models\Order;
use App\Queries\OrderStatisticsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderStatisticsController extends Controller
{
    public function __invoke(OrderStatisticsRequest $request, OrderStatisticsQuery $statistics): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        return response()->json(['data' => $statistics->forMonth($request->string('month')->toString())]);
    }
}
