<?php


namespace App\Http\Controllers;

use App\Services\AuthentificationServiceInterface;
use Illuminate\Http\Request;

class AuthController extends Controller {
    protected $authService;

    public function __construct(AuthentificationServiceInterface $authService) {
        $this->authService = $authService;
        $this->authService->setAuthMode(config('app.auth_mode'));
    }

    public function login(Request $request)
{
    $credentials = $request->only('email', 'password');
    $authResponse = $this->authService->authenticate($credentials);

    if (is_array($authResponse) && isset($authResponse['success'])) {
        if ($authResponse['success']) {
            return response()->json([
                'access_token' => $authResponse['token'],
                'token_type' => 'Bearer',
                'user' => $authResponse['user'],
            ]);
        } elseif (isset($authResponse['require_password_change']) && $authResponse['require_password_change']) {
            return response()->json([
                'success' => false,
                'message' => $authResponse['message'],
                'require_password_change' => true
            ], 200);
        }
    }

    return response()->json(['error' => $authResponse['message'] ?? 'Unauthorized'], 401);
}

    public function logout() {
        $this->authService->logout();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    
}
