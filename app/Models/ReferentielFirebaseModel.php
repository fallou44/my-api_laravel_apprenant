<?php

namespace App\Models;

use Google\Cloud\Firestore\FieldValue;
use Kreait\Firebase\Contract\Firestore;

class ReferentielFirebaseModel implements FirebaseModelInterface
{
    protected $firestore;
    protected $collection = 'referentiels';

    public function __construct(Firestore $firestore)
    {
        $this->firestore = $firestore->database();
    }

    public function create(array $data)
    {
        if ($this->isCodeOrLibelleExists($data['code'], $data['libelle'])) {
            throw new \Exception('Le code ou le libellé existe déjà.');
        }

        $referentielData = [
            'code' => $data['code'],
            'libelle' => $data['libelle'],
            'description' => $data['description'],
            'photo_couverture' => $data['photo_couverture'],
            'statut' => $data['statut'] ?? 'Actif',
            'competences' => [],
        ];

        if (isset($data['competences'])) {
            foreach ($data['competences'] as $competenceData) {
                $competence = $this->createCompetence($competenceData);
                $referentielData['competences'][] = $competence;
            }
        }

        $docRef = $this->firestore->collection($this->collection)->add($referentielData);
        return $docRef;
    }

    

    public function update(string $id, array $data)
    {
        $docRef = $this->firestore->collection($this->collection)->document($id);
        $currentData = $docRef->snapshot()->data();

        if (isset($data['code']) && $data['code'] !== $currentData['code']) {
            if ($this->isCodeExists($data['code'])) {
                throw new \Exception('Le code existe déjà.');
            }
        }

        if (isset($data['libelle']) && $data['libelle'] !== $currentData['libelle']) {
            if ($this->isLibelleExists($data['libelle'])) {
                throw new \Exception('Le libellé existe déjà.');
            }
        }

        $docRef->set($data, ['merge' => true]);
        return $docRef->snapshot();
    }

    private function isCodeOrLibelleExists($code, $libelle)
    {
        $codeQuery = $this->firestore->collection($this->collection)->where('code', '==', $code)->documents();
        $libelleQuery = $this->firestore->collection($this->collection)->where('libelle', '==', $libelle)->documents();

        return !$codeQuery->isEmpty() || !$libelleQuery->isEmpty();
    }

    private function isCodeExists($code)
    {
        $query = $this->firestore->collection($this->collection)->where('code', '==', $code)->documents();
        return !$query->isEmpty();
    }

    private function isLibelleExists($libelle)
    {
        $query = $this->firestore->collection($this->collection)->where('libelle', '==', $libelle)->documents();
        return !$query->isEmpty();
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

    public function addCompetence(string $referentielId, array $competenceData)
    {
        $referentiel = $this->find($referentielId);
        if (!$referentiel->exists()) {
            throw new \Exception("Référentiel non trouvé");
        }

        $competence = $this->createCompetence($competenceData);
        
        return $this->firestore->collection($this->collection)->document($referentielId)->update([
            ['path' => 'competences', 'value' => FieldValue::arrayUnion([$competence])]
        ]);
    }

    protected function createCompetence(array $data)
    {
        $competence = [
            'nom' => $data['nom'],
            'duree_acquisition' => $data['duree_acquisition'],
            'description' => $data['description'],
            'type' => $data['type'],
            'modules' => [],
        ];

        if (isset($data['modules'])) {
            foreach ($data['modules'] as $moduleData) {
                $competence['modules'][] = $this->createModule($moduleData);
            }
        }

        return $competence;
    }

    protected function createModule(array $data)
    {
        return [
            'nom' => $data['nom'],
            'description' => $data['description'],
            // Ajoutez d'autres champs de module si nécessaire
        ];
    }

    public function addModule(string $referentielId, string $competenceNom, array $moduleData)
    {
        $referentiel = $this->find($referentielId);
        if (!$referentiel->exists()) {
            throw new \Exception("Référentiel non trouvé");
        }

        $referentielData = $referentiel->data();
        $competenceIndex = array_search($competenceNom, array_column($referentielData['competences'], 'nom'));

        if ($competenceIndex === false) {
            throw new \Exception("Compétence non trouvée");
        }

        $module = $this->createModule($moduleData);

        return $this->firestore->collection($this->collection)->document($referentielId)->update([
            ['path' => "competences.{$competenceIndex}.modules", 'value' => FieldValue::arrayUnion([$module])]
        ]);
    }

    public function updateReferentielStatus(string $referentielId, string $newStatus)
    {
        if (!in_array($newStatus, ['Actif', 'Inactif', 'Archiver'])) {
            throw new \Exception("Statut invalide");
        }

        return $this->firestore->collection($this->collection)->document($referentielId)->update([
            ['path' => 'statut', 'value' => $newStatus]
        ]);
    }

    public function getAllReferentiels()
    {
        return $this->firestore->collection($this->collection)->documents();
    }

    public function filterByStatus(string $status)
    {
        return $this->firestore->collection($this->collection)
            ->where('statut', '=', $status)
            ->documents();
    }

    public function softDeleteCompetence(string $referentielId, string $competenceNom)
    {
        $referentiel = $this->find($referentielId);
        if (!$referentiel->exists()) {
            throw new \Exception("Référentiel non trouvé");
        }

        $referentielData = $referentiel->data();
        $competenceIndex = array_search($competenceNom, array_column($referentielData['competences'], 'nom'));

        if ($competenceIndex === false) {
            throw new \Exception("Compétence non trouvée");
        }

        // Marquer la compétence comme supprimée
        $referentielData['competences'][$competenceIndex]['deleted'] = true;

        return $this->firestore->collection($this->collection)->document($referentielId)->update([
            ['path' => 'competences', 'value' => $referentielData['competences']]
        ]);
    }

    public function softDelete(string $id)
    {
        $referentiel = $this->find($id);
        if (!$referentiel->exists()) {
            throw new \Exception("Référentiel non trouvé");
        }

        // Marquer le référentiel comme supprimé
        return $this->firestore->collection($this->collection)->document($id)->update([
            ['path' => 'deleted', 'value' => true]
        ]);
    }

    public function getArchivedReferentiels()
    {
        return $this->firestore->collection($this->collection)
            ->where('deleted', '=', true)
            ->documents();
    }
}