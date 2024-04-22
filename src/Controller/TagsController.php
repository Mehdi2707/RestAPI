<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Repository\ModelsRepository;
use App\Repository\TagRepository;
use App\Service\VersioningService;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;

class TagsController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer la liste des tags
     *
     * @param TagRepository $tagRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[OA\Response(
        response: 200,
        description: "Retourne la liste des tags",
        content: new Model(type: Tag::class, groups: ["getTags"])
    )]
    #[OA\Parameter(
        name: "term",
        description: "Mots clés pour effectuer une recherche",
        in: "query",
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Tag(name: "Tags")]
    #[Route('/api/tags', name: 'tags', methods: ['GET'])]
    public function getTagList(TagRepository $tagRepository, SerializerInterface $serializer, Request $request, VersioningService $versioningService): JsonResponse
    {
        $term = $request->get('term', '');

        $version = $versioningService->getVersion();
        $tagList = $tagRepository->findAllWithSearch($term);
        $context = SerializationContext::create()->setGroups(['getTags']);
        $context->setVersion($version);
        $jsonTagList = $serializer->serialize($tagList, 'json', $context);

        return new JsonResponse($jsonTagList, Response::HTTP_OK, [], true);
    }
}
