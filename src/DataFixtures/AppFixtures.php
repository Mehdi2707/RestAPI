<?php

namespace App\DataFixtures;

use App\Entity\File;
use App\Entity\Images;
use App\Entity\Models;
use App\Entity\Tag;
use App\Entity\Users;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $user = new Users();
        $user->setEmail("user@modelapi.com");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $manager->persist($user);

        $userAdmin = new Users();
        $userAdmin->setEmail("admin@modelapi.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $manager->persist($userAdmin);

        $listModel = [];

        for ($i = 0; $i < 20; $i++) {
            $model = new Models();
            $model->setTitle('Modèle ' . $i);
            $model->setCreatedAt(new \DateTimeImmutable());
            $model->setDescription('Description du modèle ' . $i);
            $model->setSlug('modele-' . $i);
            $manager->persist($model);
            $listModel[] = $model;
        }

        for ($i = 0; $i < 20; $i++) {
            $image = new Images();
            $image->setName('image' . $i . '.webp');
            $image->setModel($listModel[array_rand($listModel)]);
            $manager->persist($image);
        }

        for ($i = 0; $i < 20; $i++) {
            $file = new File();
            $file->setName('fichier' . $i . '.stl');
            $file->setModel($listModel[array_rand($listModel)]);
            $manager->persist($file);
        }

        $listTag = [];

        for ($i = 0; $i < 20; $i++) {
            $tag = new Tag();
            $tag->setName('tag-' . $i);
            $tag->addModel($listModel[array_rand($listModel)]);
            $manager->persist($tag);
            $listTag[] = $tag;
        }

        foreach ($listModel as $model) {
            $model->addTag($listTag[array_rand($listTag)]);
            $manager->persist($model);
        }

        $manager->flush();
    }
}
