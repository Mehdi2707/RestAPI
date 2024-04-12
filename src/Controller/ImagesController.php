<?php

namespace App\Controller;

use App\Entity\Images;
use App\Repository\ImagesRepository;
use App\Service\PictureService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ImagesController extends AbstractController
{
    #[Route('/api/images', name: 'images', methods: ['GET'])]
    public function getImagesList(ImagesRepository $imagesRepository, SerializerInterface $serializer): JsonResponse
    {
        $imageList = $imagesRepository->findAll();
        $jsonImageList = $serializer->serialize($imageList, 'json', ['groups' => 'getImages']);

        return new JsonResponse($jsonImageList, Response::HTTP_OK, [], true);
    }

    #[Route('api/images/{id}', name: 'detailsImage', methods: ['GET'])]
    public function getDetailsImage(Images $image, SerializerInterface $serializer): JsonResponse
    {
        $jsonImage = $serializer->serialize($image, 'json', ['groups' => 'getImages']);
        return new JsonResponse($jsonImage, Response::HTTP_OK, [], true);
    }

    #[Route('/api/images/{id}', name: 'deleteImage', methods: ['DELETE'])]
    public function deleteImage(Images $images, EntityManagerInterface $em, PictureService $pictureService, TagAwareCacheInterface $cache): JsonResponse
    {
        $pictureService->delete($images->getName(), 'models', 400, 400);
        $em->remove($images);
        $em->flush();

        $cache->invalidateTags(['modelsCache']);
        $cache->invalidateTags(['modelCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
