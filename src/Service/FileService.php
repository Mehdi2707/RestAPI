<?php

namespace App\Service;

use App\Entity\Users;
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

    public function add(UploadedFile $file, Users $user, ?string $folder, string $modelTitle)
    {
        $originalFilename = $file->getClientOriginalName();
        $path = $this->params->get('images_directory') . $folder;

        if(!file_exists($path . '/' . $user->getUsername() . '/' . $modelTitle . '/'))
            mkdir($path . '/' . $user->getUsername() . '/' . $modelTitle . '/', 0755, true);

        $newFile = md5(uniqid(rand(), true)) . '.' . $file->getClientOriginalExtension();

        $file->move($path . '/' . $user->getUsername() . '/' . $modelTitle . '/', $originalFilename);

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

    public function move(Users $user, string $currentSlug, string $slug, string $filename)
    {
        $success = false;
        $path = $this->params->get('images_directory') . 'models/' . $user->getUsername();
        $oldPath = $path . '/' . $currentSlug;
        $newPath = $path . '/' . $slug;

        if(!file_exists($newPath . '/'))
            mkdir($newPath . '/', 0755, true);

        if(file_exists($oldPath . '/' . $filename))
        {
            rename($oldPath . '/' . $filename, $newPath . '/' . $filename);
            $success = true;
        }
        return $success;
    }
}