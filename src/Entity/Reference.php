<?php

namespace App\Entity;

use App\Enum\Material;
use App\Enum\Printer;
use App\Repository\ReferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Uid\Uuid;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: ReferenceRepository::class)]
#[ORM\Table(name: 'reference')]
#[UniqueEntity(fields: ['slug'], message: 'Dieser Slug ist bereits vergeben.')]
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

    #[Vich\UploadableField(mapping: 'reference_images_landscape', fileNameProperty: 'imageLandscape')]
    #[Assert\Image(
        maxSize: '12M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        minWidth: 1080,
        minHeight: 864,
        maxPixels: 30000000,
        minRatio: 1.2375,
        maxRatio: 1.2625,
        mimeTypesMessage: 'Erlaubt sind JPEG, PNG und WebP.',
        minWidthMessage: 'Das Bild muss mindestens 1080 Pixel breit sein ({{ width }} ist zu wenig).',
        minHeightMessage: 'Das Bild muss mindestens 864 Pixel hoch sein ({{ height }} ist zu wenig).',
        minRatioMessage: 'Das Bild muss im Seitenverhältnis 5:4 vorliegen ({{ ratio }} ist zu hoch).',
        maxRatioMessage: 'Das Bild muss im Seitenverhältnis 5:4 vorliegen ({{ ratio }} ist zu breit).'
    )]
    protected ?File $imageFileLandscape = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $imageLandscape = null;

    #[Vich\UploadableField(mapping: 'reference_images_portrait', fileNameProperty: 'imagePortrait')]
    #[Assert\Image(
        maxSize: '12M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        minWidth: 1080,
        minHeight: 1350,
        maxPixels: 30000000,
        minRatio: 0.792,
        maxRatio: 0.808,
        mimeTypesMessage: 'Erlaubt sind JPEG, PNG und WebP.',
        minWidthMessage: 'Das Bild muss mindestens 1080 Pixel breit sein ({{ width }} ist zu wenig).',
        minHeightMessage: 'Das Bild muss mindestens 1350 Pixel hoch sein ({{ height }} ist zu wenig).',
        minRatioMessage: 'Das Bild muss im Seitenverhältnis 4:5 vorliegen ({{ ratio }} ist zu schmal).',
        maxRatioMessage: 'Das Bild muss im Seitenverhältnis 4:5 vorliegen ({{ ratio }} ist zu breit).'
    )]
    protected ?File $imageFilePortrait = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $imagePortrait = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'datetime')]
    protected \DateTimeInterface $createdAt;

    #[ORM\Embedded(class: Source::class, columnPrefix: 'source_')]
    protected ?Source $source = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        message: 'Der Slug darf nur Kleinbuchstaben, Ziffern und einzelne Bindestriche enthalten.'
    )]
    protected string $slug = '';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $isVisible = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $summary = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Assert\NotNull(message: 'Eine Kategorie ist erforderlich.')]
    protected ?Category $category = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, enumType: Material::class)]
    protected ?Material $material = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, enumType: Printer::class)]
    protected ?Printer $printer = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $ratingUrl = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = (new \DateTime())->setTime(0, 0, 0);
        $this->isVisible = false;
        // The admin form binds to the property paths source.title, source.url
        // and source.author, so the embeddable has to exist from the start.
        $this->source = new Source();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getImageFileLandscape(): ?File
    {
        return $this->imageFileLandscape;
    }

    public function setImageFileLandscape(?File $imageFileLandscape = null): self
    {
        $this->imageFileLandscape = $imageFileLandscape;

        if ($imageFileLandscape !== null) {
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function getImageLandscape(): ?string
    {
        return $this->imageLandscape;
    }

    public function setImageLandscape(?string $imageLandscape): self
    {
        $this->imageLandscape = $imageLandscape;
        return $this;
    }

    public function getImageFilePortrait(): ?File
    {
        return $this->imageFilePortrait;
    }

    public function setImageFilePortrait(?File $imageFilePortrait = null): self
    {
        $this->imageFilePortrait = $imageFilePortrait;

        if ($imageFilePortrait !== null) {
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function getImagePortrait(): ?string
    {
        return $this->imagePortrait;
    }

    public function setImagePortrait(?string $imagePortrait): self
    {
        $this->imagePortrait = $imagePortrait;
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

    public function getImageLandscapePath(): ?string
    {
        return $this->imageLandscape !== null
            ? '/images/references/landscape/' . $this->imageLandscape
            : null;
    }

    public function getImagePortraitPath(): ?string
    {
        return $this->imagePortrait !== null
            ? '/images/references/portrait/' . $this->imagePortrait
            : null;
    }

    /**
     * The portrait image is shown wherever elements stack vertically. Older
     * references have no portrait image, so the landscape one stands in.
     */
    public function getImagePortraitPathWithFallback(): ?string
    {
        return $this->getImagePortraitPath() ?? $this->getImageLandscapePath();
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

    public function hasSource(): bool
    {
        return $this->source !== null
            && $this->source->getTitle() !== null
            && $this->source->getUrl() !== null
            && $this->source->getAuthor() !== null;
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

    public function getRatingUrl(): ?string
    {
        return $this->ratingUrl;
    }

    public function setRatingUrl(?string $ratingUrl): self
    {
        $this->ratingUrl = $ratingUrl;
        return $this;
    }

    public function getMaterialLabel(): ?string
    {
        if ($this->material === null) {
            return null;
        }

        $label = $this->material->value;

        if ($this->printer?->isMultiColor()) {
            $label .= ', mehrfarbig';
        }

        return $label;
    }

    /**
     * Both formats are mandatory. The check accepts either a freshly uploaded
     * file or an already stored one, because VichUploader only writes the file
     * name while flushing, long after validation has run.
     */
    #[Assert\Callback]
    public function validateImages(ExecutionContextInterface $context): void
    {
        if ($this->imageFileLandscape === null && ($this->imageLandscape ?? '') === '') {
            $context->buildViolation('Ein Bild im Querformat ist erforderlich.')
                ->atPath('imageFileLandscape')
                ->addViolation();
        }

        if ($this->imageFilePortrait === null && ($this->imagePortrait ?? '') === '') {
            $context->buildViolation('Ein Bild im Hochformat ist erforderlich.')
                ->atPath('imageFilePortrait')
                ->addViolation();
        }
    }

    #[ORM\PrePersist]
    public function normalizeSource(): void
    {
        if ($this->source === null) {
            return;
        }

        $isEmpty = ($this->source->getTitle() ?? '') === ''
            && ($this->source->getUrl() ?? '') === ''
            && ($this->source->getAuthor() ?? '') === '';

        if ($isEmpty) {
            $this->source = null;
        }
    }

    #[ORM\PrePersist]
    public function generateSlugValue(): void
    {
        if ($this->slug === '') {
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
