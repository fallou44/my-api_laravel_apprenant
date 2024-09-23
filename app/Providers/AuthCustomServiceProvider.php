<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AuthentificationPassport;
use App\Services\AuthentificationSanctum;
use App\Services\AuthentificationServiceInterface;

class AuthCustomServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Here you can choose which implementation to bind
        // For Passport:
      $this->app->bind(AuthentificationServiceInterface::class, AuthentificationPassport::class);  

        // For Sanctum:
     //$this->app->bind(AuthentificationServiceInterface::class, AuthentificationSanctum::class);  
    }

    public function boot()
    {
        // Any additional boot logic
    }
}