<?php

namespace App\Services;

use App\Enums\AdminUserStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BootstrapSuperAdminService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function create(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            if (User::query()->exists()) {
                throw new DomainException('The initial super administrator can only be created when no staff accounts exist.');
            }

            app(RoleSeeder::class)->run();
            app(PermissionSeeder::class)->run();

            $superAdminRole = Role::query()->where('slug', 'super-admin')->firstOrFail();
            $user = User::query()->create([
                'name' => $name,
                'email' => strtolower($email),
                'password' => Hash::make($password),
                'status' => AdminUserStatus::Active,
            ]);

            $user->roles()->attach($superAdminRole);
            $this->auditLogService->record(null, 'admin.bootstrap', $user);

            return $user;
        });
    }
}
