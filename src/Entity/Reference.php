<?php

namespace App\Entity;

use App\Enum\Material;
use App\Enum\Printer;
use App\Repository\ReferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ReferenceRepository::class)]
#[ORM\Table(name: 'reference')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Reference
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    protected ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $title = '';

    #[ORM\Column(type: 'text')]
    protected string $description = '';

    #[Vich\UploadableField(mapping: 'reference_images', fileNameProperty: 'image')]
    protected ?File $imageFile = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $image = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'datetime')]
    protected \DateTimeInterface $createdAt;

    #[ORM\Embedded(class: Source::class, columnPrefix: 'source_')]
    protected ?Source $source = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    protected string $slug = '';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $isVisible = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $summary = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    protected ?Category $category = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, enumType: Material::class)]
    protected ?Material $material = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, enumType: Printer::class)]
    protected ?Printer $printer = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->source = new Source();
        $this->createdAt = (new \DateTime())->setTime(0, 0, 0);
        $this->isVisible = false;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile = null): self
    {
        $this->imageFile = $imageFile;

        if ($imageFile) {
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->image ? '/images/references/' . $this->image : null;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function setSource(?Source $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getYear(): int
    {
        return (int) $this->createdAt->format('Y');
    }

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): self
    {
        $this->isVisible = $isVisible;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getMaterial(): ?Material
    {
        return $this->material;
    }

    public function setMaterial(?Material $material): self
    {
        $this->material = $material;
        return $this;
    }

    public function getPrinter(): ?Printer
    {
        return $this->printer;
    }

    public function setPrinter(?Printer $printer): self
    {
        $this->printer = $printer;
        return $this;
    }

    public function getMaterialLabel(): ?string
    {
        if (!$this->material) {
            return null;
        }

        $label = $this->material->value;

        if ($this->printer?->isMultiColor()) {
            $label .= ', mehrfarbig';
        }

        return $label;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (!$this->createdAt) {
            $this->createdAt = (new \DateTime())->setTime(0, 0, 0);
        }

        if (empty($this->slug)) {
            $slugger = new AsciiSlugger('de');
            $this->slug = strtolower($slugger->slug($this->title)->toString());
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
