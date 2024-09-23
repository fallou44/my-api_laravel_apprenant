<?php


namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AuthentificationPassport implements AuthentificationServiceInterface {
    public function authenticate(array $credentials)
{
    $validator = Validator::make($credentials, [
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);

    if ($validator->fails()) {
        return [
            'success' => false,
            'errors' => $validator->errors()
        ];
    }

    if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
        $user = User::where('email', $credentials['email'])->first();

        if ($user->statut === 'Inactif') {
            return [
                'success' => false,
                'message' => 'Vous devez changer votre mot de passe pour activer votre compte.',
                'require_password_change' => true
            ];
        }

        $token = $user->createToken('LaravelPassportAuth')->accessToken;
        return [
            'success' => true,
            'user' => $user,
            'token' => $token
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Invalid credentials'
        ];
    }
}


    public function logout() {
        // Implémenter la logique de déconnexion pour Passport
    }

    public function setAuthMode(string $mode) {
        // Pas nécessaire pour cette implémentation
    }
}
