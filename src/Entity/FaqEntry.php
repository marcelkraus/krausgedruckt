<?php

namespace App\Entity;

use App\Repository\FaqEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FaqEntryRepository::class)]
#[ORM\Table(name: 'faq_entry')]
#[ORM\Index(name: 'idx_sort_order', columns: ['sort_order'])]
#[ORM\HasLifecycleCallbacks]
class FaqEntry
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    protected ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Die Frage darf nicht leer sein.')]
    #[Assert\Length(max: 255, maxMessage: 'Die Frage darf maximal {{ limit }} Zeichen lang sein.')]
    protected string $question = '';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Die Antwort darf nicht leer sein.')]
    protected string $answer = '';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $isVisible = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    protected int $sortOrder = 0;

    #[ORM\Column(type: 'datetime')]
    protected \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->isVisible = false;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): self
    {
        $this->question = $question;
        return $this;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): self
    {
        $this->answer = $answer;
        return $this;
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
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

    // Alias method for template compatibility
    public function isActive(): bool
    {
        return $this->isVisible();
    }

    public function getSortButtons(): string
    {
        return ''; // Overridden by controller
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
