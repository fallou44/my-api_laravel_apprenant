<?php

namespace App\Models;

use Kreait\Firebase\Contract\Firestore;

class PromotionFirebaseModel implements FirebaseModelInterface
{
    protected $firestore;
    protected $collection = 'promotions';

    public function __construct(Firestore $firestore)
    {
        $this->firestore = $firestore->database();
    }

    public function create(array $data)
    {
        if ($this->isLibelleExists($data['libelle'])) {
            throw new \Exception('Le libellé de la promotion existe déjà.');
        }
    
        $promotionData = [
            'libelle' => $data['libelle'],
            'dateDebut' => $data['dateDebut'],
            'dateFin' => $data['dateFin'],
            'duree' => $data['duree'],
            'etat' => 'Inactif',
            'photo_couverture' => $data['photo_couverture'] ?? null,
            'referentiels' => [],
            'apprenants' => [],
        ];
    
        if (isset($data['referentiels'])) {
            foreach ($data['referentiels'] as $referentielData) {
                $referentiel = $this->findReferentiel($referentielData['id']);
                if (!$referentiel->exists() || (isset($referentiel->data()['deleted']) && $referentiel->data()['deleted'])) {
                    throw new \Exception('Le référentiel n\'est plus valable.');
                }
    
                $competences = $referentielData['competences'] ?? [];
                foreach ($competences as $competenceNom) {
                    if (!$this->competenceExists($referentiel->data()['competences'], $competenceNom)) {
                        throw new \Exception('La compétence "' . $competenceNom . '" n\'existe pas dans le référentiel.');
                    }
                }
    
                $promotionData['referentiels'][] = [
                    'id' => $referentielData['id'],
                    'competences' => $competences,
                ];
            }
        }
    
        if (isset($data['apprenants'])) {
            foreach ($data['apprenants'] as $apprenantId) {
                if (!$this->apprenantExists($apprenantId)) {
                    throw new \Exception('L\'apprenant avec l\'ID ' . $apprenantId . ' n\'existe pas.');
                }
    
                $apprenant = $this->firestore->collection('apprenants')->document($apprenantId)->snapshot();
                $promotionData['apprenants'][] = $apprenant->data();
            }
    
            // Si des apprenants sont ajoutés, mettre à jour l'état de la promotion à "Actif"
            $promotionData['etat'] = 'Actif';
        }
    
        return $this->firestore->collection($this->collection)->add($promotionData);
    }
    


    public function update(string $id, array $data)
{
    $promotion = $this->find($id);
    if (!$promotion->exists()) {
        throw new \Exception('La promotion n\'existe pas.');
    }

    if (isset($data['libelle']) && $data['libelle'] !== $promotion->data()['libelle']) {
        if ($this->isLibelleExists($data['libelle'])) {
            throw new \Exception('Le libellé de la promotion existe déjà.');
        }
    }

    $promotionData = array_merge($promotion->data(), $data);

    if (isset($data['referentiels'])) {
        $promotionData['referentiels'] = [];
        foreach ($data['referentiels'] as $referentielData) {
            $referentiel = $this->findReferentiel($referentielData['id']);
            if (!$referentiel->exists() || (isset($referentiel->data()['deleted']) && $referentiel->data()['deleted'])) {
                throw new \Exception('Le référentiel n\'est plus valable.');
            }

            $competences = $referentielData['competences'] ?? [];
            foreach ($competences as $competenceNom) {
                if (!$this->competenceExists($referentiel->data()['competences'], $competenceNom)) {
                    throw new \Exception('La compétence "' . $competenceNom . '" n\'existe pas dans le référentiel.');
                }
            }

            $promotionData['referentiels'][] = [
                'id' => $referentielData['id'],
                'competences' => $competences,
            ];
        }
    }

    if (isset($data['apprenants'])) {
        $promotionData['apprenants'] = [];
        foreach ($data['apprenants'] as $apprenantId) {
            if (!$this->apprenantExists($apprenantId)) {
                throw new \Exception('L\'apprenant avec l\'ID ' . $apprenantId . ' n\'existe pas.');
            }

            $apprenant = $this->firestore->collection('apprenants')->document($apprenantId)->snapshot();
            $promotionData['apprenants'][] = $apprenant->data();
        }

        // Si des apprenants sont ajoutés, mettre à jour l'état de la promotion à "Actif"
        $promotionData['etat'] = 'Actif';
    }

    return $this->firestore->collection($this->collection)->document($id)->set($promotionData, ['merge' => true]);
}


    public function delete(string $id)
    {
        return $this->firestore->collection($this->collection)->document($id)->delete();
    }

    public function find(string $id)
    {
        return $this->firestore->collection($this->collection)->document($id)->snapshot();
    }

    public function findByField(string $field, $value)
    {
        return $this->firestore->collection($this->collection)
            ->where($field, '=', $value)
            ->documents();
    }

    public function getAllPromotions()
{
    return $this->firestore->collection($this->collection)->documents();
}


    protected function findReferentiel(string $id)
    {
        return $this->firestore->collection('referentiels')->document($id)->snapshot();
    }

    protected function competenceExists(array $competences, string $competenceNom)
    {
        foreach ($competences as $competence) {
            if ($competence['nom'] === $competenceNom) {
                return true;
            }
        }
        return false;
    }

    protected function isLibelleExists(string $libelle)
    {
        $query = $this->firestore->collection($this->collection)->where('libelle', '==', $libelle)->documents();
        return !$query->isEmpty();
    }

    protected function apprenantExists(string $apprenantId)
{
    $apprenant = $this->firestore->collection('apprenants')->document($apprenantId)->snapshot();
    return $apprenant->exists();
}

public function updateReferentiels(string $id, array $data)
{
    $promotion = $this->find($id);
    if (!$promotion->exists()) {
        throw new \Exception('La promotion n\'existe pas.');
    }

    $promotionData = $promotion->data();

    if (isset($data['add'])) {
        foreach ($data['add'] as $referentielData) {
            $referentiel = $this->findReferentiel($referentielData['id']);
            if (!$referentiel->exists() || (isset($referentiel->data()['deleted']) && $referentiel->data()['deleted'])) {
                throw new \Exception('Le référentiel n\'est plus valable.');
            }

            // Vérifier si le référentiel est déjà présent dans la promotion
            $referentielIndex = array_search($referentielData['id'], array_column($promotionData['referentiels'], 'id'));
            if ($referentielIndex !== false) {
                throw new \Exception('Le référentiel est déjà présent dans la promotion.');
            }

            $competences = $referentielData['competences'] ?? [];
            foreach ($competences as $competenceNom) {
                if (!$this->competenceExists($referentiel->data()['competences'], $competenceNom)) {
                    throw new \Exception('La compétence "' . $competenceNom . '" n\'existe pas dans le référentiel.');
                }
            }

            $promotionData['referentiels'][] = [
                'id' => $referentielData['id'],
                'competences' => $competences,
            ];
        }
    }

    if (isset($data['remove'])) {
        foreach ($data['remove'] as $referentielId) {
            $referentielIndex = array_search($referentielId, array_column($promotionData['referentiels'], 'id'));
            if ($referentielIndex === false) {
                throw new \Exception('Le référentiel n\'existe pas dans la promotion.');
            }

            // Vérifier si le référentiel a des apprenants
            $referentiel = $promotionData['referentiels'][$referentielIndex];
            if (!empty($referentiel['apprenants'])) {
                throw new \Exception('Le référentiel contient des apprenants et ne peut pas être retiré.');
            }

            // Marquer le référentiel comme supprimé
            $promotionData['referentiels'][$referentielIndex]['deleted'] = true;
        }
    }

    return $this->firestore->collection($this->collection)->document($id)->set($promotionData, ['merge' => true]);
}

public function updateStatus(string $id, string $newStatus)
{
    if (!in_array($newStatus, ['Actif', 'Inactif'])) {
        throw new \Exception("Statut invalide");
    }

    $promotion = $this->find($id);
    if (!$promotion->exists()) {
        throw new \Exception("Promotion non trouvée");
    }

    if ($newStatus === 'Actif') {
        // Vérifier s'il y a déjà une promotion active
        $activePromotions = $this->firestore->collection($this->collection)
            ->where('etat', '=', 'Actif')
            ->documents();

        if (!$activePromotions->isEmpty()) {
            throw new \Exception("Il y a déjà une promotion active. Vous ne pouvez pas avoir plus d'une promotion active à la fois.");
        }
    }

    $promotionData = $promotion->data();
    $promotionData['etat'] = $newStatus;

    return $this->firestore->collection($this->collection)->document($id)->set($promotionData, ['merge' => true]);
}

public function getPromotionActive()
{
    $promotions = $this->firestore->collection($this->collection)
        ->where('etat', '=', 'Actif')
        ->documents();

    foreach ($promotions as $promotion) {
        return $promotion;
    }

    return null;
}










}
