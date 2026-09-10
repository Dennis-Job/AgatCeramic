<?php

namespace App\Providers;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Policies\AttributeGroupPolicy;
use App\Policies\AttributePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ContactRequestPolicy;
use App\Policies\OrderPolicy;
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
    #[\Override]
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
        Gate::policy(ContactRequest::class, ContactRequestPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('login', static function (Request $request): Limit {
            $email = $request->input('email', '');
            $identity = strtolower(is_string($email) ? $email : '').'|'.$request->ip();

            return Limit::perMinute(5)->by(hash('sha256', $identity));
        });

        RateLimiter::for('password-reset', static function (Request $request): Limit {
            $email = $request->input('email', '');
            $identity = strtolower(is_string($email) ? $email : '').'|'.$request->ip();

            return Limit::perMinute(5)->by(hash('sha256', $identity));
        });

        RateLimiter::for('order-create', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('callback-request', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('email-request', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('partner-request', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        ResetPassword::createUrlUsing(static function (mixed $user, string $token): string {
            $adminUrl = config('admin.url');

            if (! $user instanceof User || ! is_string($adminUrl)) {
                throw new \LogicException('Password reset URL configuration is invalid.');
            }

            return rtrim($adminUrl, '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }
}
