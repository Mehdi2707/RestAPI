<?php

namespace App\Controller;

use App\Entity\Models;
use App\Repository\ModelsRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;

class ModelsController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des modèles
     *
     * @param ModelsRepository $modelsRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @param VersioningService $versioningService
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/models', name: 'models', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: "Retourne la liste des modèles",
        content: new Model(type: Models::class, groups: ["getModels"])
    )]
    #[OA\Parameter(
        name: "page",
        description: "La page que l'on veut récupérer",
        in: "query",
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Parameter(
        name: "limit",
        description: "Le nombre d'éléments que l'on veut récupérer",
        in: "query",
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Tag(name: "Models")]
    public function getModelList(ModelsRepository $modelsRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache, VersioningService $versioningService): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getModelList-" . $page . "-" . $limit;

        $jsonModelList = $cache->get($idCache, function (ItemInterface $item) use ($modelsRepository, $page, $limit, $serializer, $versioningService) {
            $item->tag("modelsCache");
            $version = $versioningService->getVersion();
            $modelList = $modelsRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getModels']);
            $context->setVersion($version);
            return $serializer->serialize($modelList, 'json', $context);
        });

        return new JsonResponse($jsonModelList, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de récupérer un modèle en particulier en fonction de son id.
     *
     * @param Models $model
     * @param SerializerInterface $serializer
     * @param TagAwareCacheInterface $cache
     * @param VersioningService $versioningService
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[OA\Response(
        response: 200,
        description: "Retourne un modèle",
        content: new Model(type: Models::class, groups: ["getModels"])
    )]
    #[OA\Tag(name: "Models")]
    #[Route('api/models/{id}', name: 'detailsModel', methods: ['GET'])]
    public function getDetailsModel(Models $model, SerializerInterface $serializer, TagAwareCacheInterface $cache, VersioningService $versioningService): JsonResponse
    {
        $idCache = "getDetailsModel-" . $model->getId();

        $jsonModelDetails = $cache->get($idCache, function (ItemInterface $item) use ($model, $serializer, $versioningService) {
            $item->tag("modelCache");
            $version = $versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['getModels']);
            $context->setVersion($version);
            return $serializer->serialize($model, 'json', $context);
        });

        return new JsonResponse($jsonModelDetails, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de supprimer un modèle par rapport à son id.
     *
     * @param Models $model
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/models/{id}', name: 'deleteModel', methods: ['DELETE'])]
    #[OA\Response(
        response: 204,
        description: "Supprime un modèle"
    )]
    #[OA\Tag(name: "Models")]
    public function deleteBook(Models $model, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(['modelsCache']);
        $em->remove($model);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cette méthode permet d'insérer un nouveau modèle.
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/models', name:"createModel", methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: "Créer un modèle",
        content: new Model(type: Models::class, groups: ["getModels"])
    )]
    #[OA\RequestBody(
        required: true,
        content: new Model(type: Models::class)
    )]
    #[OA\Tag(name: "Models")]
    #[IsGranted('ROLE_ADMIN', message: "Vous n\'avez pas les droits suffisants pour créer un modèle")]
    public function createModel(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, TagAwareCacheInterface $cache, SluggerInterface $slugger): JsonResponse
    {
        $model = $serializer->deserialize($request->getContent(), Models::class, 'json');

        $model->setCreatedAt(new \DateTimeImmutable());
        $model->setSlug($slugger->slug($model->getTitle())->lower());

        $errors = $validator->validate($model);

        if($errors->count() > 0)
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);

        if(array_key_exists("images", $request->toArray()))
            foreach ($model->getImages() as $image) {
                $em->persist($image);
            }

        $em->persist($model);
        $em->flush();

        $cache->invalidateTags(['modelsCache']);

        $context = SerializationContext::create()->setGroups(['getModels']);

        $jsonModel = $serializer->serialize($model, 'json', $context);

        $location = $urlGenerator->generate('detailsModel', ['id' => $model->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonModel, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de mettre à jour un modèle en fonction de son id.
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param Models $model
     * @param EntityManagerInterface $em
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/models/{id}', name:"updateModel", methods:['PUT'])]
    #[OA\Response(
        response: 204,
        description: "Met à jour un modèle"
    )]
    // Attribut pour mettre a jour un modèle à faire
    #[OA\Tag(name: "Models")]
    #[IsGranted('ROLE_ADMIN', message: "Vous n\'avez pas les droits suffisants pour éditer un modèle")]
    public function updateModel(Request $request, SerializerInterface $serializer, Models $model, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache, SluggerInterface $slugger): JsonResponse
    {
        $updatedModel = $serializer->deserialize($request->getContent(),Models::class,'json');
        $model->setTitle($updatedModel->getTitle());
        $model->setDescription($updatedModel->getDescription());
        $model->setSlug($slugger->slug($updatedModel->getTitle())->lower());

        $errors = $validator->validate($model);

        if($errors->count() > 0)
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);

        // GESTION DES IMAGES A FAIRE

        $em->persist($updatedModel);
        $em->flush();

        $cache->invalidateTags(['modelsCache']);
        $cache->invalidateTags(['modelCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
