<?php

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: FileRepository::class)]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getModels", "getImages", "getFiles", "getTags"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getModels", "getImages", "getFiles", "getTags"])]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["getImages", "getFiles", "getTags"])]
    private ?Models $model = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getModel(): ?Models
    {
        return $this->model;
    }

    public function setModel(?Models $model): static
    {
        $this->model = $model;

        return $this;
    }
}
