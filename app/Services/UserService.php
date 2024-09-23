<?php

// app/Services/UserService.php

namespace App\Services;

use App\Models\Apprenant;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase\Contract\Storage;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Firestore;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Jobs\SendApprenantRegisteredEmail;

class UserService
{
    protected $userRepository;
    protected $cloudinaryService;
    protected $auth;
    protected $firestore;
    protected $storage;

    public function __construct(
        UserRepository $userRepository,
        CloudinaryService $cloudinaryService,
        Auth $auth,
        Firestore $firestore,
        Storage $storage
    ) {
        $this->userRepository = $userRepository;
        $this->cloudinaryService = $cloudinaryService;
        $this->auth = $auth;
        $this->firestore = $firestore;
        $this->storage = $storage;
    }

    public function createUser(array $data, ?UploadedFile $image = null)
    {
        // Check if user exists in Firebase Authentication
        try {
            $firebaseUser = $this->auth->getUserByEmail($data['email']);
            throw new \Exception('This email is already in use.');
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            // User not found, proceed to create
        }

        // Create user in Firebase Authentication
        $firebaseUser = $this->auth->createUser([
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Prepare data for local database
        $userData = [
            'firebase_uid' => $firebaseUser->uid,
            'prenom' => $data['prenom'],
            'nom' => $data['nom'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? 'Coach',
        ];

        if ($userData['role'] === 'Apprenant') {
            $userData['statut'] = 'Inactif';
        } else {
            $userData['statut'] = 'actif';
        }

        if ($image) {
            $imageUrl = $this->cloudinaryService->uploadImage($image);
            $userData['image_url'] = $imageUrl;
        }

        // Save user in local database
        $localUser = User::create($userData);

        return [
            'firebaseUser' => $firebaseUser,
            'localUser' => $localUser
        ];
    }

    public function updateUser(string $id, array $data, ?UploadedFile $image = null)
    {
        // Find the user in the local database
        $user = User::find($id);
        if (!$user) {
            throw new \Exception('User not found.');
        }

        // Prepare data for update
        $userData = [];

        if (isset($data['prenom'])) {
            $userData['prenom'] = $data['prenom'];
        }
        if (isset($data['nom'])) {
            $userData['nom'] = $data['nom'];
        }
        if (isset($data['email'])) {
            $userData['email'] = $data['email'];
            // Update email in Firebase Authentication
            $this->auth->updateUser($user->firebase_uid, [
                'email' => $data['email'],
            ]);
        }
        if (isset($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
            // Update password in Firebase Authentication
            $this->auth->updateUser($user->firebase_uid, [
                'password' => $data['password'],
            ]);
        }
        if (isset($data['role'])) {
            $userData['role'] = $data['role'];
        }
        if ($image) {
            $imageUrl = $this->cloudinaryService->uploadImage($image);
            $userData['image_url'] = $imageUrl;
        }

        // Update user in local database
        $user->update($userData);

        return $user;
    }

    public function createApprenant(array $data, ?UploadedFile $image = null, array $pdfs)
{
    $data['role'] = 'Apprenant';
    $data['statut'] = 'Inactif';

    // Vérifier si l'apprenant existe déjà
    $existingUser = User::where('email', $data['email'])->first();
    if ($existingUser) {
        throw new \Exception('L\'apprenant avec cet email existe déjà.');
    }

    $userResult = $this->createUser($data, $image);
    $firebaseUser = $userResult['firebaseUser'];
    $localUser = $userResult['localUser'];

    $localUser->statut = 'Inactif';
    $localUser->save();

    $pdfUrls = $this->uploadPdfs($pdfs, $firebaseUser->uid);

    $matricule = $this->generateMatricule();
    $qrCodeUrl = $this->generateQrCode($localUser, $matricule);

    $apprenantData = [
        'user_id' => $localUser->id,
        'nom_tuteur' => $data['nom_tuteur'],
        'prenom_tuteur' => $data['prenom_tuteur'],
        'contact_tuteur' => $data['contact_tuteur'],
        'photocopie_cni' => $pdfUrls['photocopie_cni'],
        'diplome' => $pdfUrls['diplome'],
        'extrait_naissance' => $pdfUrls['extrait_naissance'],
        'casier_judiciaire' => $pdfUrls['casier_judiciaire'],
        'matricule' => $matricule,
        'qr_code' => $qrCodeUrl,
    ];

    $apprenant = Apprenant::create($apprenantData);

    $this->firestore->database()->collection('apprenants')->add([
        'user' => [
            'firebase_uid' => $firebaseUser->uid,
            'prenom' => $localUser->prenom,
            'nom' => $localUser->nom,
            'email' => $localUser->email,
            'role' => $localUser->role,
            'image_url' => $localUser->image_url ?? null,
        ],
        'nom_tuteur' => $data['nom_tuteur'],
        'prenom_tuteur' => $data['prenom_tuteur'],
        'contact_tuteur' => $data['contact_tuteur'],
        'photocopie_cni' => $pdfUrls['photocopie_cni'],
        'diplome' => $pdfUrls['diplome'],
        'extrait_naissance' => $pdfUrls['extrait_naissance'],
        'casier_judiciaire' => $pdfUrls['casier_judiciaire'],
        'matricule' => $matricule,
        'qr_code' => $qrCodeUrl,
    ]);

    // Dispatcher le job pour envoyer la notification par email
    SendApprenantRegisteredEmail::dispatch($localUser, $data['password']);

    return [
        'user' => $localUser,
        'apprenant' => $apprenant,
        'firebase_uid' => $firebaseUser->uid
    ];
}


    private function uploadPdfs(array $pdfs, string $userId)
    {
        $urls = [];
        foreach ($pdfs as $key => $pdf) {
            $path = "apprenants/{$userId}/{$key}.pdf";
            $this->storage->getBucket()->upload(
                fopen($pdf->getPathname(), 'r'),
                ['name' => $path]
            );
            $urls[$key] = $this->storage->getBucket()->object($path)->signedUrl(new \DateTime('+ 10 years'));
        }
        return $urls;
    }

    private function generateMatricule()
    {
        // Générer un matricule unique
        return 'APP-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    }

    private function generateQrCode(User $user, string $matricule)
    {
        // Générer un code QR contenant les informations de l'apprenant
        $data = [
            'matricule' => $matricule,
            'prenom' => $user->prenom,
            'nom' => $user->nom,
            'email' => $user->email,
            'role' => $user->role,
        ];

        $qrCode = QrCode::format('png')->size(200)->generate(json_encode($data));

        // Enregistrer le code QR dans un fichier temporaire
        $tempFilePath = tempnam(sys_get_temp_dir(), 'qr_code_') . '.png';
        file_put_contents($tempFilePath, $qrCode);

        // Uploader le fichier temporaire sur Firebase Storage
        $path = 'apprenants/' . $user->id . '/qr_code.png';
        $this->storage->getBucket()->upload(
            fopen($tempFilePath, 'r'),
            ['name' => $path]
        );

        // Supprimer le fichier temporaire
        unlink($tempFilePath);

        return $this->storage->getBucket()->object($path)->signedUrl(new \DateTime('+ 10 years'));
    }

    public function changePassword(string $email, string $currentPassword, string $newPassword)
{
    $user = User::where('email', $email)->first();

    if (!$user || !Hash::check($currentPassword, $user->password)) {
        throw new \Exception('Mot de passe actuel incorrect');
    }

    if ($user->statut !== 'Inactif') {
        throw new \Exception('Le changement de mot de passe n\'est nécessaire que pour les comptes inactifs');
    }

    // Vérification des critères du nouveau mot de passe
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{5,}$/', $newPassword)) {
        throw new \Exception('Le nouveau mot de passe ne respecte pas les critères requis');
    }

    $user->password = Hash::make($newPassword);
    $user->statut = 'Actif';
    $user->save();

    // Mise à jour du mot de passe dans Firebase
    $this->auth->changeUserPassword($user->firebase_uid, $newPassword);

    return $user;
}




}
