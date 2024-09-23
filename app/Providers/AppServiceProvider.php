<?php

namespace App\Providers;

use App\Repositories\UserRepository;
use App\Services\AuthentificationFirebase;
use App\Services\AuthentificationPassport;
use App\Services\AuthentificationServiceInterface;
use App\Services\CloudinaryService;
use App\Services\UserService;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Configurer Firestore
        $this->app->singleton(Firestore::class, function ($app) {
            $factory = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
            return $factory->createFirestore();
        });

        // Configurer l'authentification
        $this->app->bind(AuthentificationServiceInterface::class, AuthentificationPassport::class);

        $this->app->singleton(AuthentificationServiceInterface::class, function ($app) {
            if (config('app.auth_mode') === 'firebase') {
                $firebaseFactory = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
                $auth = $firebaseFactory->createAuth();
                return new AuthentificationFirebase($auth);
            }
            return new AuthentificationPassport();
        });

        // Configurer UserService avec Firestore
        $this->app->singleton(UserService::class, function ($app) {
            $firebaseFactory = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
            $auth = $firebaseFactory->createAuth();
            $firestore = $firebaseFactory->createFirestore();
            $storage = $firebaseFactory->createStorage();
            return new UserService(
                $app->make(UserRepository::class),
                $app->make(CloudinaryService::class),
                $auth,
                $firestore,
                $storage
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
