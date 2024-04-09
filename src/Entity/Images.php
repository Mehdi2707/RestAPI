<?php

namespace App\Entity;

use App\Repository\ImagesRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ImagesRepository::class)]
class Images
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getModels", "getImages"])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images', cascade: ["persist"])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["getImages"])]
    private ?Models $model = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getModels", "getImages"])]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModel(): ?Models
    {
        return $this->model;
    }

    public function setModel(?Models $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
