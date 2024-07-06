<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\Images;
use App\Entity\Models;
use App\Entity\Tag;
use App\Repository\ModelsRepository;
use App\Repository\TagRepository;
use App\Service\FileService;
use App\Service\PictureService;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
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
use OpenApi\Attributes as OA;

class ModelsController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer des modèles
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
    #[OA\Parameter(
        name: "term",
        description: "Mots clés pour effectuer une recherche",
        in: "query",
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: "tag",
        description: "Tag associé à un modèle pour effectuer une recherche",
        in: "query",
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Tag(name: "Models")]
    public function getModelList(ModelsRepository $modelsRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache, VersioningService $versioningService): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $term = $request->get('term', '');
        $tag = $request->get('tag', '');

        $idCache = "getModelList-" . $page . "-" . $limit . "-" . $term . "-" . $tag;

        $jsonModelList = $cache->get($idCache, function (ItemInterface $item) use ($modelsRepository, $page, $limit, $term, $tag, $serializer, $versioningService) {
            $item->tag("modelsCache");
            $version = $versioningService->getVersion();
            $modelList = $modelsRepository->findAllWithPaginationAndSearchOrTag($page, $limit, $term, $tag);
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
     * @param PictureService $pictureService
     * @param FileService $fileService
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/models/{id}', name: 'deleteModel', methods: ['DELETE'])]
    #[OA\Response(
        response: 204,
        description: "Supprime un modèle"
    )]
    #[OA\Tag(name: "Models")]
    public function deleteModel(Models $model, EntityManagerInterface $em, TagAwareCacheInterface $cache, PictureService $pictureService, FileService $fileService): JsonResponse
    {
        $cache->invalidateTags(['modelsCache']);
        $cache->invalidateTags(['modelCache']);

        foreach($model->getImages() as $image)
        {
            $pictureService->delete($image->getName(), 'models', 400, 400);
            $em->remove($image);
        }

        foreach($model->getFiles() as $file)
        {
            $fileService->delete($file->getName(), 'models/' . $this->getUser()->getUsername() . '/' . $model->getSlug());
            $em->remove($file);
        }

        $fileService->delete($model->getFile(), 'models');
        rmdir($this->getParameter('images_directory') . 'models/' . $this->getUser()->getUsername() . '/' . $model->getSlug());

        $em->remove($model);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cette méthode permet d'insérer un nouveau modèle.
     *
     * @param Request $request
     * @param Security $security
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param PictureService $pictureService
     * @param TagRepository $tagRepository
     * @param FileService $fileService
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @param SluggerInterface $slugger
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
    public function createModel(Request $request, Security $security, SerializerInterface $serializer, EntityManagerInterface $em, PictureService $pictureService, TagRepository $tagRepository, FileService $fileService, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, TagAwareCacheInterface $cache, SluggerInterface $slugger): JsonResponse
    {
        $modelData = $request->request->all();

        $model = new Models();
        $model->setCreatedAt(new \DateTimeImmutable());
        $model->setSlug($slugger->slug($modelData['title'])->lower());
        $model->setTitle($modelData['title']);
        $model->setDescription($modelData['description']);
        $model->setUser($security->getUser());

        $errors = $validator->validate($model);

        if($errors->count() > 0)
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);

        if($request->files->get('images')) {
            foreach ($request->files->get('images') as $imageData) {
                $folder = 'models';
                $file = $pictureService->add($imageData, $folder, 400, 400);

                $image = new Images();
                $image->setName($file);
                $model->addImage($image);
            }
        }

        $zip = new \ZipArchive();
        $zipFilename = $model->getSlug() . ' - ' . rand() . '.zip';

        if ($zip->open($zipFilename, \ZipArchive::CREATE) !== true) {
            throw new \Exception('Cannot create zip file');
        }

        if($request->files->get('files')) {
            foreach ($request->files->get('files') as $fileData) {
                $folder = 'models';
                $uploadedFile = $fileService->add($fileData, $security->getUser(), $folder, $model->getSlug());

                $filePath = $this->getParameter('images_directory') . 'models/' . $security->getUser()->getUsername() . '/' . $model->getSlug() . '/' . $uploadedFile;
                $zip->addFile($filePath, basename($filePath));

                $file = new File();
                $file->setName($uploadedFile);
                $model->addFile($file);
            }
        }

        $zip->close();

        $finalZipPath = $this->getParameter('images_directory') . 'models/' . basename($zipFilename);
        rename($zipFilename, $finalZipPath);

        $model->setFile($zipFilename);

        if(array_key_exists('tags', $modelData)) {
            foreach ($modelData['tags'] as $tag) {

                $tagExist = $tagRepository->findOneBy(['name' => $tag]);

                if($tagExist)
                {
                    $model->addTag($tagExist);
                }
                else
                {
                    $newTag = new Tag();
                    $newTag->setName($tag);
                    $newTag->addModel($model);
                }
            }
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
     * @param Security $security
     * @param SerializerInterface $serializer
     * @param PictureService $pictureService
     * @param FileService $fileService
     * @param TagRepository $tagRepository
     * @param Models $model
     * @param EntityManagerInterface $em
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @param SluggerInterface $slugger
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/models/{id}', name:"updateModel", methods:['PUT', 'POST'])]
    #[OA\Response(
        response: 204,
        description: "Met à jour un modèle"
    )]
    // Attribut pour mettre a jour un modèle à faire
    #[OA\Tag(name: "Models")]
    #[IsGranted('ROLE_ADMIN', message: "Vous n\'avez pas les droits suffisants pour éditer un modèle")]
    public function updateModel(Request $request, Security $security, SerializerInterface $serializer, PictureService $pictureService, FileService $fileService, TagRepository $tagRepository, Models $model, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache, SluggerInterface $slugger): JsonResponse
    {
        $updatedModel = $request->request->all();
        $currentTags = $model->getTags();
        $currentSlug = $model->getSlug();
        $currentFiles = $model->getFiles();

        foreach ($currentTags as $tagModel) {
            $model->removeTag($tagModel);
        }

        $model->setSlug($slugger->slug($updatedModel['title'])->lower());
        $model->setTitle($updatedModel['title']);
        $model->setDescription($updatedModel['description']);

        $errors = $validator->validate($model);

        if($errors->count() > 0)
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);

        if($request->files->get('images')) {
            foreach ($request->files->get('images') as $imageData) {
                $folder = 'models';
                $file = $pictureService->add($imageData, $folder, 400, 400);

                $image = new Images();
                $image->setName($file);
                $model->addImage($image);
            }
        }

        $fileService->delete($model->getFile(), 'models');

        $zip = new \ZipArchive();
        $zipFilename = $model->getSlug() . ' - ' . rand() . '.zip';

        if ($zip->open($zipFilename, \ZipArchive::CREATE) !== true) {
            throw new \Exception('Cannot create zip file');
        }

        if($request->files->get('files')) {
            foreach ($request->files->get('files') as $fileData) {
                $folder = 'models';
                $uploadedFile = $fileService->add($fileData, $security->getUser(), $folder, $model->getSlug());

                $filePath = $this->getParameter('images_directory') . 'models/' . $security->getUser()->getUsername() . '/' . $model->getSlug() . '/' . $uploadedFile;
                $zip->addFile($filePath, basename($filePath));

                $file = new File();
                $file->setName($uploadedFile);
                $model->addFile($file);
            }
        }

        foreach ($currentFiles as $file)
        {
            $filePath = $this->getParameter('images_directory') . 'models/' . $security->getUser()->getUsername() . '/' . $currentSlug . '/' . $file->getName();
            $zip->addFile($filePath, basename($filePath));
        }

        $zip->close();

        $finalZipPath = $this->getParameter('images_directory') . 'models/' . basename($zipFilename);
        rename($zipFilename, $finalZipPath);

        $model->setFile($zipFilename);

        if($currentSlug !== $model->getSlug())
        {
            foreach ($currentFiles as $file)
            {
                $fileService->move($security->getUser(), $currentSlug, $model->getSlug(), $file->getName());
            }
            rmdir($this->getParameter('images_directory') . 'models/' . $security->getUser()->getUsername() . '/' . $currentSlug . '/');
        }

        if(array_key_exists('tags', $updatedModel)) {
            foreach ($updatedModel['tags'] as $tag) {

                $tagExist = $tagRepository->findOneBy(['name' => $tag]);

                if($tagExist)
                {
                    $model->addTag($tagExist);
                }
                else
                {
                    $newTag = new Tag();
                    $newTag->setName($tag);
                    $newTag->addModel($model);
                }
            }
        }

        $em->persist($model);
        $em->flush();

        $cache->invalidateTags(['modelsCache']);
        $cache->invalidateTags(['modelCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
