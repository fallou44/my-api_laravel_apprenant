<?php

namespace App\Http\Controllers;

use App\Models\ReferentielFirebaseModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ReferentielController extends Controller
{
    protected $referentielModel;

    public function __construct(ReferentielFirebaseModel $referentielModel)
    {
        $this->referentielModel = $referentielModel;
    }

    public function index(Request $request)
    {
        try {
            $referentiels = $this->referentielModel->getAllReferentiels();
            $data = [];
            foreach ($referentiels as $referentiel) {
                $referentielData = array_merge(['id' => $referentiel->id()], $referentiel->data());

                // Vérifier si le référentiel est marqué comme supprimé
                if (isset($referentielData['deleted']) && $referentielData['deleted']) {
                    continue;
                }

                // Filtrer les compétences supprimées
                if (isset($referentielData['competences'])) {
                    $referentielData['competences'] = array_filter($referentielData['competences'], function ($competence) {
                        return !isset($competence['deleted']) || !$competence['deleted'];
                    });

                    // Réindexer le tableau pour éviter les clés manquantes
                    $referentielData['competences'] = array_values($referentielData['competences']);
                }

                $data[] = $referentielData;
            }

            if (empty($data)) {
                return response()->json(['message' => 'Aucun référentiel trouvé'], 200);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {

        if (Gate::denies('manage-referentiels')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:255',
            'libelle' => 'required|string|max:255',
            'description' => 'required|string',
            'photo_couverture' => 'required|string',
            'competences' => 'sometimes|array',
            'competences.*.nom' => 'required|string|max:255',
            'competences.*.duree_acquisition' => 'required|string',
            'competences.*.description' => 'required|string',
            'competences.*.type' => 'required|string',
            'competences.*.modules' => 'sometimes|array',
            'competences.*.modules.*.nom' => 'required|string|max:255',
            'competences.*.modules.*.description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        try {
            $referentiel = $this->referentielModel->create($request->all());
            return response()->json([
                'message' => 'Référentiel créé avec succès',
                'id' => $referentiel->id()
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    public function update(Request $request, $id)
    {
        if (Gate::denies('manage-referentiels')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:255',
            'libelle' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'photo_couverture' => 'sometimes|string',
            'statut' => 'sometimes|in:Actif,Inactif,Archiver',
            'competences' => 'sometimes|array',
            'delete_competence' => 'sometimes|string', // Nom de la compétence à supprimer
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        try {
            if ($request->has('delete_competence')) {
                $this->referentielModel->softDeleteCompetence($id, $request->delete_competence);
                return response()->json(['message' => 'Compétence supprimée avec succès']);
            }

            $this->referentielModel->update($id, $request->all());
            return response()->json(['message' => 'Référentiel mis à jour avec succès']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    public function show($id)
    {
        try {
            $referentiel = $this->referentielModel->find($id);
            if (!$referentiel->exists()) {
                return response()->json(['message' => 'Référentiel non trouvé'], 404);
            }

            $referentielData = array_merge(['id' => $referentiel->id()], $referentiel->data());

            // Vérifier si le référentiel est marqué comme supprimé
            if (isset($referentielData['deleted']) && $referentielData['deleted']) {
                return response()->json(['message' => 'Référentiel supprimé'], 404);
            }

            // Filtrer les compétences supprimées
            if (isset($referentielData['competences'])) {
                $referentielData['competences'] = array_filter($referentielData['competences'], function ($competence) {
                    return !isset($competence['deleted']) || !$competence['deleted'];
                });

                // Réindexer le tableau pour éviter les clés manquantes
                $referentielData['competences'] = array_values($referentielData['competences']);
            }

            return response()->json($referentielData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function listModules($id)
    {
        try {
            $referentiel = $this->referentielModel->find($id);
            if (!$referentiel->exists()) {
                return response()->json(['message' => 'Référentiel non trouvé'], 404);
            }

            $referentielData = $referentiel->data();
            $modules = [];

            if (isset($referentielData['competences'])) {
                foreach ($referentielData['competences'] as $competence) {
                    if (isset($competence['modules'])) {
                        $modules = array_merge($modules, $competence['modules']);
                    }
                }
            }

            return response()->json($modules);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    

    public function destroy($id)
    {
        if (Gate::denies('manage-referentiels')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        try {
            $this->referentielModel->softDelete($id);
            return response()->json(['message' => 'Référentiel supprimé avec succès']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function addCompetence(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'duree_acquisition' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|string',
            'modules' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        try {
            $this->referentielModel->addCompetence($id, $request->all());
            return response()->json(['message' => 'Compétence ajoutée avec succès']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function addModule(Request $request, $referentielId, $competenceNom)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        try {
            $this->referentielModel->addModule($referentielId, $competenceNom, $request->all());
            return response()->json(['message' => 'Module ajouté avec succès']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'statut' => 'required|in:Actif,Inactif,Archiver',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        try {
            $this->referentielModel->updateReferentielStatus($id, $request->statut);
            return response()->json(['message' => 'Statut du référentiel mis à jour avec succès']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function archive(Request $request)
    {
        try {
            $referentiels = $this->referentielModel->getArchivedReferentiels();
            $data = [];
            foreach ($referentiels as $referentiel) {
                $referentielData = array_merge(['id' => $referentiel->id()], $referentiel->data());

                // Filtrer les compétences supprimées
                if (isset($referentielData['competences'])) {
                    $referentielData['competences'] = array_filter($referentielData['competences'], function ($competence) {
                        return !isset($competence['deleted']) || !$competence['deleted'];
                    });

                    // Réindexer le tableau pour éviter les clés manquantes
                    $referentielData['competences'] = array_values($referentielData['competences']);
                }

                $data[] = $referentielData;
            }

            if (empty($data)) {
                return response()->json(['message' => 'Aucun référentiel archivé trouvé'], 200);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
}