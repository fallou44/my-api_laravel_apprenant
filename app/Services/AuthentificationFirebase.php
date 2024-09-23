<?php


namespace App\Services;

use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\FirebaseException;
use Illuminate\Support\Facades\Log;

class AuthentificationFirebase implements AuthentificationServiceInterface {
    protected $auth;

    public function __construct(Auth $auth) {
        $this->auth = $auth;
    }

    public function authenticate(array $credentials)
{
    try {
        $signInResult = $this->auth->signInWithEmailAndPassword($credentials['email'], $credentials['password']);
        $user = $this->auth->getUser($signInResult->data()['localId']);
        
        return [
            'success' => true,
            'user' => [
                'id' => $user->uid,
                'name' => $user->displayName,
                'email' => $user->email,
                // Ajoutez d'autres champs utilisateur si nécessaire
            ],
            'token' => $signInResult->data()['idToken']
        ];
    } catch (FirebaseException $e) {
        Log::error('Firebase authentication error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Invalid credentials'
        ];
    }
}

    public function logout() {
        // Implémenter la logique de déconnexion pour Firebase
    }

    public function setAuthMode(string $mode) {
        // Pas nécessaire pour cette implémentation
    }
}
