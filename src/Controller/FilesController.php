<?php

namespace App\Controller;

use App\Entity\File;
use App\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class FilesController extends AbstractController
{
    #[Route('/api/files/{id}', name: 'deleteFile', methods: ['DELETE'])]
    public function deleteFile(File $file, EntityManagerInterface $em, FileService $fileService, TagAwareCacheInterface $cache): JsonResponse
    {
        $fileService->delete($file->getName(), 'models');
        $em->remove($file);
        $em->flush();

        $cache->invalidateTags(['modelsCache']);
        $cache->invalidateTags(['modelCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
