<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

abstract class Controller
{
    /**
     * Return the active administrator guaranteed by the protected route group.
     *
     * The runtime check keeps the controller boundary safe even if a protected
     * action is later moved outside the current middleware group.
     */
    protected function authenticatedAdmin(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * Return a single uploaded file after the corresponding Form Request has
     * validated the field as required and file.
     */
    protected function uploadedFile(Request $request, string $key): UploadedFile
    {
        $file = $request->file($key);

        if (! $file instanceof UploadedFile) {
            abort(422, "The {$key} upload is invalid.");
        }

        return $file;
    }
}
