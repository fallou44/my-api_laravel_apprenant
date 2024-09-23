<?php

// app/Imports/ApprenantsImport.php
namespace App\Imports;

use App\Models\Apprenant;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Http\UploadedFile;

class ApprenantsImport implements ToCollection, WithHeadingRow
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $data = [
                'prenom' => $row['prenom'],
                'nom' => $row['nom'],
                'email' => $row['email'],
                'password' => $row['password'],
                'nom_tuteur' => $row['nom_tuteur'],
                'prenom_tuteur' => $row['prenom_tuteur'],
                'contact_tuteur' => $row['contact_tuteur'],
            ];

            $pdfs = [
                'photocopie_cni' => new UploadedFile($row['photocopie_cni'], 'photocopie_cni.pdf'),
                'diplome' => new UploadedFile($row['diplome'], 'diplome.pdf'),
                'extrait_naissance' => new UploadedFile($row['extrait_naissance'], 'extrait_naissance.pdf'),
                'casier_judiciaire' => new UploadedFile($row['casier_judiciaire'], 'casier_judiciaire.pdf'),
            ];

            $this->userService->createApprenant($data, null, $pdfs);
        }
    }
}
