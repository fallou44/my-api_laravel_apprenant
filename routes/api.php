<?php


use App\Http\Controllers\AuthController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ProtectedController;
use App\Http\Controllers\ReferentielController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/test-firebase', function () {
    try {
        // Créer une instance de Firestore
        $factory = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
        $firestore = $factory->createFirestore()->database();

        // Essayons d'écrire quelque chose dans Firestore
        $docRef = $firestore->collection('test')->document('connection_test');
        $docRef->set([
            'timestamp' => time(),
            'message' => 'Test de connexion réussi'
        ]);

        // Lisons ce que nous venons d'écrire
        $snapshot = $docRef->snapshot();
        $value = $snapshot->data();

        return response()->json([
            'success' => true,
            'message' => 'Connexion à Firebase réussie',
            'data' => $value
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur de connexion à Firebase: ' . $e->getMessage()
        ], 500);
    }
});

Route::post('/user', [UserController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/change-password', [UserController::class, 'changePassword']);

Route::middleware([config('app.auth_mode') === 'firebase' ? 'auth.firebase' : 'auth:api'])->group(function () {
    
    Route::patch('/v1/users/{id}', [UserController::class, 'update']);
    Route::get('/v1/users', [UserController::class, 'index']);
    Route::post('/apprenants', [UserController::class, 'storeApprenant']);

    
    Route::post('/v1/apprenants/import', [UserController::class, 'importApprenants']);
    Route::get('/v1/apprenants', [PromotionController::class, 'getApprenantsFromActivePromotion']);

    
    
    Route::get('/referentiels', [ReferentielController::class, 'index']);
    Route::post('/referentiels', [ReferentielController::class, 'store']);
    Route::get('/referentiels/{id}', [ReferentielController::class, 'show']);
    Route::get('/referentiels/{id}/modules', [ReferentielController::class, 'listModules']);
    
    Route::put('/referentiels/{id}', [ReferentielController::class, 'update']);
    Route::delete('/referentiels/{id}', [ReferentielController::class, 'destroy']);
    
    Route::post('/referentiels/{id}/competences', [ReferentielController::class, 'addCompetence']);
    //Route::post('/referentiels/{referentielId}/competences/{competenceNom}/modules', [ReferentielController::class, 'addModule']);
    //Route::patch('/referentiels/{id}/status', [ReferentielController::class, 'updateStatus']);
    Route::patch('/referentiels/{id}', [ReferentielController::class, 'update']);
    Route::get('/archive/referentiels', [ReferentielController::class, 'archive']);
    
    
    
    Route::get('/promotions', [PromotionController::class, 'index']);
    Route::post('/promotions', [PromotionController::class, 'store']);
    Route::get('/promotions/{id}', [PromotionController::class, 'show']);
    Route::patch('/promotions/{id}', [PromotionController::class, 'update']);
    Route::delete('/promotions/{id}', [PromotionController::class, 'destroy']);
    Route::patch('/v1/promotions/{id}/referentiels', [PromotionController::class, 'updateReferentiels']);
    Route::patch('/v1/promotions/{id}/etat', [PromotionController::class, 'updateStatus']);
    Route::get('/v1/promotions', [PromotionController::class, 'index']);
    Route::get('/v1/promotions/encour', [PromotionController::class, 'getPromotionActive']);
    

});




//Route::post('/test-firebase-auth', [AuthController::class, 'testFirebaseAuth']);
