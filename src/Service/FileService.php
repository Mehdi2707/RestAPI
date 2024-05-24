<?php

namespace App\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileService
{
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function add(UploadedFile $file, ?string $folder = '')
    {
        $originalFilename = $file->getClientOriginalName();
        // TODO Voir pour mettre un nom de fichier unique et faire un dossier pour chaque modele...
        $newFile = md5(uniqid(rand(), true)) . '.' . $file->getClientOriginalExtension();

        $path = $this->params->get('images_directory') . $folder;

        $file->move($path . '/', $originalFilename);

        return $originalFilename;
    }

    public function delete(string $file, ?string $folder = '')
    {
        if($file !== 'default.webp')
        {
            $success = false;
            $path = $this->params->get('images_directory') . $folder;

            $original = $path . '/' . $file;
            if(file_exists($original))
            {
                unlink($original);
                $success = true;
            }
            return $success;
        }
        return false;
    }
}