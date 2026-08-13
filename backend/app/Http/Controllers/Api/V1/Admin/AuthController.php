<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Admin\LoginRequest;
use App\Http\Requests\Api\V1\Admin\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCurrentAdminProfileRequest;
use App\Http\Resources\AdminUserResource;
use App\Services\AdminAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AdminAuthenticationService $authenticationService) {}

    public function login(LoginRequest $request): Response
    {
        $this->authenticationService->login($request);

        return response()->noContent();
    }

    public function forgotPassword(ForgotPasswordRequest $request): Response
    {
        $this->authenticationService->sendPasswordResetLink($request);

        return response()->noContent();
    }

    public function resetPassword(ResetPasswordRequest $request): Response
    {
        $this->authenticationService->resetPassword($request);

        return response()->noContent();
    }

    public function me(Request $request): AdminUserResource
    {
        return new AdminUserResource($request->user()->load('roles.permissions'));
    }

    public function updateProfile(UpdateCurrentAdminProfileRequest $request): AdminUserResource
    {
        $attributes = $request->validated();
        $user = $this->authenticationService->updateProfile($request->user(), $attributes);

        if (array_key_exists('password', $attributes)) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return new AdminUserResource($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authenticationService->logout($request);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
