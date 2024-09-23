<?php

namespace App\Providers;

use App\Models\FirebaseModelImpl;
use App\Models\FirebaseModelInterface;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Firestore;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(FirebaseModelInterface::class, function ($app) {
            return new FirebaseModelImpl(
                $app->make(Firestore::class),
                'users' // Default collection, can be changed as needed
            );
        });

        $this->app->bind(UserRepository::class, function ($app) {
            return new UserRepository($app->make(FirebaseModelInterface::class));
        });
    }
}