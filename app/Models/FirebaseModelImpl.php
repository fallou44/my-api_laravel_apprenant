<?php

namespace App\Models;
use Kreait\Firebase\Contract\Firestore;

class FirebaseModelImpl implements FirebaseModelInterface
{
    protected $firestore;
    protected $collection;

    public function __construct(Firestore $firestore, string $collection)
    {
        $this->firestore = $firestore->database();
        $this->collection = $collection;
    }

    public function create(array $data)
    {
        return $this->firestore->collection($this->collection)->add($data);
    }

    public function update(string $id, array $data)
    {
        return $this->firestore->collection($this->collection)->document($id)->set($data, ['merge' => true]);
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
}