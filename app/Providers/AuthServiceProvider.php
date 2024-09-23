<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('create-user', function (User $user, string $role) {
            if ($user->role === 'Admin') {
                return in_array($role, ['Admin', 'Coach', 'Manager', 'CM']);
            } elseif ($user->role === 'Manager') {
                return in_array($role, ['Coach', 'Manager', 'CM']);
            }
            return false;
        });

        Gate::define('create-apprenant', function (User $user) {
            return in_array($user->role, ['Admin', 'CM', 'Manager']);
        });

        Gate::define('import-apprenants', function (User $user) {
            return in_array($user->role, ['Admin', 'CM', 'Manager']);
        });

        Gate::define('manage-promotions', function ($user) {
            return in_array($user->role, ['Admin', 'CM']);
        });

        Gate::define('manage-referentiels', function ($user) {
            return in_array($user->role, ['Admin', 'CM']);
        });
    }
    
}
