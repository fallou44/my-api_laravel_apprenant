<?php

// app/Repositories/UserRepository.php
namespace App\Repositories;

use App\Models\FirebaseModelInterface;

class UserRepository
{
    protected $firebaseModel;

    public function __construct(FirebaseModelInterface $firebaseModel)
    {
        $this->firebaseModel = $firebaseModel;
    }

    public function create(array $data)
    {
        return $this->firebaseModel->create($data);
    }

    public function update(string $id, array $data)
    {
        return $this->firebaseModel->update($id, $data);
    }

    public function findByEmail(string $email)
    {
        return $this->firebaseModel->findByField('email', $email);
    }

}