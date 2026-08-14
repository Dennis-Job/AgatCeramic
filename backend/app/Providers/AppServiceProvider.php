<?php

namespace App\Providers;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Policies\AttributeGroupPolicy;
use App\Policies\AttributePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(AttributeGroup::class, AttributeGroupPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('login', static function (Request $request): Limit {
            $identity = strtolower($request->input('email', '')).'|'.$request->ip();

            return Limit::perMinute(5)->by(hash('sha256', $identity));
        });

        RateLimiter::for('password-reset', static function (Request $request): Limit {
            $identity = strtolower($request->input('email', '')).'|'.$request->ip();

            return Limit::perMinute(5)->by(hash('sha256', $identity));
        });

        ResetPassword::createUrlUsing(static function (User $user, string $token): string {
            return rtrim((string) config('admin.url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }
}
