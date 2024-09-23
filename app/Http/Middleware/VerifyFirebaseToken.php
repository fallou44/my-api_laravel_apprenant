<?php

namespace App\Http\Middleware;

use Closure;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Auth;

class VerifyFirebaseToken
{
    protected $auth;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
        $this->auth = $factory->createAuth();
    }

    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'No token provided'], 401);
        }

        try {
            $verifiedIdToken = $this->auth->verifyIdToken($token);
            $uid = $verifiedIdToken->claims()->get('sub');
            
            // Ici, vous devriez récupérer l'utilisateur correspondant dans votre base de données
            // et le définir comme l'utilisateur authentifié pour cette requête
            // Par exemple :
            // $user = User::where('firebase_uid', $uid)->first();
            // Auth::login($user);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
    }
}