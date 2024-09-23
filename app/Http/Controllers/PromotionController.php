<?php

namespace App\Http\Controllers;

use App\Models\PromotionFirebaseModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;


class PromotionController extends Controller
{
    protected $promotionModel;

    public function __construct(PromotionFirebaseModel $promotionModel)
    {
        $this->promotionModel = $promotionModel;
    }

    public function index()
{

    if (Gate::denies('manage-promotions')) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    try {
        $promotions = $this->promotionModel->getAllPromotions();
        $data = [];
        foreach ($promotions as $promotion) {
            $promotionData = array_merge(['id' => $promotion->id()], $promotion->data());

            // Exclure les promotions supprimées en soft delete
            if (isset($promotionData['deleted']) && $promotionData['deleted']) {
                continue;
            }

            $data[] = $promotionData;
        }

        if (empty($data)) {
            return response()->json(['message' => 'Aucune promotion disponible'], 200);
        }

        return response()->json($data);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


    public function store(Request $request)
{
    if (Gate::denies('manage-promotions')) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    $validator = Validator::make($request->all(), [
        'libelle' => 'required|string|max:255',
        'dateDebut' => 'required|date',
        'dateFin' => 'sometimes|date',
        'duree' => 'sometimes|integer',
        'photo_couverture' => 'nullable|string',
        'referentiels' => 'sometimes|array',
        'referentiels.*.id' => 'required|string',
        'referentiels.*.competences' => 'sometimes|array',
        'referentiels.*.competences.*' => 'required|string',
        'apprenants' => 'sometimes|array',
        'apprenants.*' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    $data = $request->all();

    // Vérifier si la date de fin ou la durée est fournie
    if (!isset($data['dateFin']) && !isset($data['duree'])) {
        return response()->json(['error' => 'Vous devez fournir soit la date de fin, soit la durée.'], 400);
    }

    // Calculer la date de fin si la durée est fournie
    if (isset($data['duree'])) {
        $dateDebut = new \DateTime($data['dateDebut']);
        $dateDebut->modify("+{$data['duree']} months");
        $data['dateFin'] = $dateDebut->format('Y-m-d');
    }

    // Calculer la durée si la date de fin est fournie
    if (isset($data['dateFin'])) {
        $dateDebut = new \DateTime($data['dateDebut']);
        $dateFin = new \DateTime($data['dateFin']);
        $interval = $dateDebut->diff($dateFin);
        $data['duree'] = $interval->y * 12 + $interval->m;
    }

    try {
        $promotion = $this->promotionModel->create($data);
        return response()->json([
            'message' => 'Promotion créée avec succès',
            'id' => $promotion->id()
        ], 201);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 409);
    }
}



    public function show($id)
    {

        try {
            $promotion = $this->promotionModel->find($id);
            if (!$promotion->exists()) {
                return response()->json(['message' => 'Promotion non trouvée'], 404);
            }
            return response()->json(array_merge(['id' => $promotion->id()], $promotion->data()));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
{
    if (Gate::denies('manage-promotions')) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    $validator = Validator::make($request->all(), [
        'libelle' => 'sometimes|string|max:255',
        'dateDebut' => 'sometimes|date',
        'dateFin' => 'sometimes|date',
        'duree' => 'sometimes|integer',
        'photo_couverture' => 'nullable|string',
        'referentiels' => 'sometimes|array',
        'referentiels.*.id' => 'required|string',
        'referentiels.*.competences' => 'sometimes|array',
        'referentiels.*.competences.*' => 'required|string',
        'apprenants' => 'sometimes|array',
        'apprenants.*' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    $data = $request->all();

    // Vérifier si la date de fin ou la durée est fournie
    if (isset($data['dateDebut']) && (!isset($data['dateFin']) && !isset($data['duree']))) {
        return response()->json(['error' => 'Vous devez fournir soit la date de fin, soit la durée.'], 400);
    }

    // Calculer la date de fin si la durée est fournie
    if (isset($data['duree'])) {
        $dateDebut = new \DateTime($data['dateDebut']);
        $dateDebut->modify("+{$data['duree']} months");
        $data['dateFin'] = $dateDebut->format('Y-m-d');
    }

    // Calculer la durée si la date de fin est fournie
    if (isset($data['dateFin'])) {
        $dateDebut = new \DateTime($data['dateDebut']);
        $dateFin = new \DateTime($data['dateFin']);
        $interval = $dateDebut->diff($dateFin);
        $data['duree'] = $interval->y * 12 + $interval->m;
    }

    try {
        $this->promotionModel->update($id, $data);
        return response()->json(['message' => 'Promotion mise à jour avec succès']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 409);
    }
}


    public function destroy($id)
    {
        if (Gate::denies('manage-promotions')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        try {
            $this->promotionModel->delete($id);
            return response()->json(['message' => 'Promotion supprimée avec succès']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateReferentiels(Request $request, $id)
{
    if (Gate::denies('manage-promotions')) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    $validator = Validator::make($request->all(), [
        'add' => 'sometimes|array',
        'add.*.id' => 'required|string',
        'add.*.competences' => 'sometimes|array',
        'add.*.competences.*' => 'required|string',
        'remove' => 'sometimes|array',
        'remove.*' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    $data = $request->all();

    try {
        $this->promotionModel->updateReferentiels($id, $data);
        return response()->json(['message' => 'Référentiels mis à jour avec succès']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function updateStatus(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'etat' => 'required|in:Actif,Inactif',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    $data = $request->all();

    try {
        $this->promotionModel->updateStatus($id, $data['etat']);
        return response()->json(['message' => 'Statut de la promotion mis à jour avec succès']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function getPromotionActive()
{
    try {
        $promotion = $this->promotionModel->getPromotionActive();
        if (!$promotion) {
            return response()->json(['message' => 'Aucune promotion active'], 200);
        }

        $promotionData = array_merge(['id' => $promotion->id()], $promotion->data());
        return response()->json($promotionData);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function getApprenantsFromActivePromotion()
{
    try {
        $promotion = $this->promotionModel->getPromotionActive();
        if (!$promotion) {
            return response()->json(['message' => 'Aucune promotion active'], 200);
        }

        $apprenants = $promotion->data()['apprenants'] ?? [];
        return response()->json($apprenants);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}








}
