<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getModels", "getImages", "getFiles", "getTags"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getModels", "getImages", "getFiles", "getTags"])]
    private ?string $name = null;

    #[ORM\ManyToMany(targetEntity: Models::class, mappedBy: 'tags')]
    #[Groups(["getImages", "getFiles", "getTags"])]
    private Collection $models;

    public function __construct()
    {
        $this->models = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, Models>
     */
    public function getModels(): Collection
    {
        return $this->models;
    }

    public function addModel(Models $model): static
    {
        if (!$this->models->contains($model)) {
            $this->models->add($model);
            $model->addTag($this);
        }

        return $this;
    }

    public function removeModel(Models $model): static
    {
        if ($this->models->removeElement($model)) {
            $model->removeTag($this);
        }

        return $this;
    }
}
