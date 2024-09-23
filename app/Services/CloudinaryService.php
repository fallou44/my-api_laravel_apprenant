<?php


namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Exception;
use Illuminate\Http\UploadedFile;

class CloudinaryService {
    /**
     * Télécharge une image vers Cloudinary.
     *
     * @param UploadedFile $image
     * @return string
     * @throws Exception
     */
    public function uploadImage(UploadedFile $image): string
    {
        try {
            return Cloudinary::upload($image->getRealPath())->getSecurePath();
        } catch (Exception $e) {
            throw new Exception('Erreur lors du téléchargement de l\'image : ' . $e->getMessage());
        }
    }


    


}