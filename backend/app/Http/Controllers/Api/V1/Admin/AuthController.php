<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\LoginRequest;
use App\Http\Resources\AdminUserResource;
use App\Services\AdminAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AdminAuthenticationService $authenticationService) {}

    public function login(LoginRequest $request): Response
    {
        $this->authenticationService->login($request);

        return response()->noContent();
    }

    public function me(Request $request): AdminUserResource
    {
        return new AdminUserResource($request->user()->load('roles.permissions'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authenticationService->logout($request);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
