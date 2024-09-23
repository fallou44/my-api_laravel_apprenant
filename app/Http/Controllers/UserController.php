<?php

namespace App\Http\Controllers;

use App\Imports\ApprenantsImport;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\UserService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        $role = $request->query('role');
        $users = User::byRole($role)->get();
        return response()->json($users);
    }

    public function store(Request $request)
    {
        if (Gate::denies('create-user', $request->input('role'))) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé à créer cet utilisateur.'
            ], 403);
        }
        // Validation des données
        $request->validate([
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'email' => 'required|email',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|in:Admin,Coach,CM,Apprenant', // Ajoutez la validation pour le rôle
        ]);

        try {
            $data = $request->only(['prenom', 'nom', 'email', 'password','role']);
            $image = $request->file('image'); // Récupérer l'image téléchargée

            // Appel au service pour créer l'utilisateur avec les données et l'image
            $this->userService->createUser($data, $image);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès dans Firebase Authentication',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Validation des données
        $request->validate([
            'prenom' => 'sometimes|string|max:255',
            'nom' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => 'sometimes|string|min:8|confirmed',
            'role' => 'sometimes|in:Admin,Coach,CM,Apprenant', // Ajoutez la validation pour le rôle
        ]);

        try {
            $data = $request->only(['prenom', 'nom', 'email', 'password','role']);
            $image = $request->file('image'); // Récupérer l'image téléchargée

            // Appel au service pour mettre à jour l'utilisateur avec les données et l'image
            $this->userService->updateUser($id, $data, $image);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeApprenant(Request $request)
    {
        if (Gate::denies('create-apprenant')) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé à créer un apprenant.'
            ], 403);
        }
        $request->validate([
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'email' => 'required|email',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => 'required|string|min:8|confirmed',
            'nom_tuteur' => 'required|string|max:255',
            'prenom_tuteur' => 'required|string|max:255',
            'photocopie_cni' => 'required|file|mimes:pdf|max:2048',
            'diplome' => 'required|file|mimes:pdf|max:2048',
            'contact_tuteur' => 'required|string|max:255',
            'extrait_naissance' => 'required|file|mimes:pdf|max:2048',
            'casier_judiciaire' => 'required|file|mimes:pdf|max:2048',
        ]);

        try {
            $data = $request->only([
                'prenom', 'nom', 'email', 'password',
                'nom_tuteur', 'prenom_tuteur', 'contact_tuteur'
            ]);

            $image = $request->file('image');
            $pdfs = [
                'photocopie_cni' => $request->file('photocopie_cni'),
                'diplome' => $request->file('diplome'),
                'extrait_naissance' => $request->file('extrait_naissance'),
                'casier_judiciaire' => $request->file('casier_judiciaire'),
            ];

            $result = $this->userService->createApprenant($data, $image, $pdfs);

            return response()->json([
                'success' => true,
                'message' => 'Apprenant créé avec succès',
                'apprenant' => $result
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'apprenant: ' . $e->getMessage()
            ], 500);
        }
    }

    public function importApprenants(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx,xls',
    ]);

    try {
        Excel::import(new ApprenantsImport($this->userService), $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Apprenants importés avec succès',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l\'importation des apprenants: ' . $e->getMessage()
        ], 500);
    }
}

public function changePassword(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'current_password' => 'required|string',
        'new_password' => [
            'required',
            'string',
            'min:5',
            'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            'confirmed'
        ],
    ]);

    try {
        $user = $this->userService->changePassword(
            $request->email,
            $request->current_password,
            $request->new_password
        );

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe changé avec succès. Votre compte est maintenant actif.',
            'user' => $user
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 400);
    }
}

}
