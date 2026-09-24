<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SignatureModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Reads `config/signature-models.json`: the campaign of the current event and
 * the models shown there. The table cards are reprinted for every event, so
 * the campaign lives beside the models rather than on each of them.
 */
final class SignatureModelCatalog
{
    /**
     * @var array{campaign: array{name: string, slug: string}, models: list<SignatureModel>}|null
     */
    private ?array $data = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/signature-models.json')]
        private readonly string $path,
    ) {
    }

    /**
     * @return array{name: string, slug: string}
     */
    public function campaign(): array
    {
        return $this->load()['campaign'];
    }

    /**
     * @return list<SignatureModel>
     */
    public function all(): array
    {
        return $this->load()['models'];
    }

    public function find(string $slug): ?SignatureModel
    {
        foreach ($this->all() as $model) {
            if ($model->slug === $slug) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @return array{campaign: array{name: string, slug: string}, models: list<SignatureModel>}
     */
    private function load(): array
    {
        if (null !== $this->data) {
            return $this->data;
        }

        $raw = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        $serializer = new Serializer([new ObjectNormalizer(), new ArrayDenormalizer()], [new JsonEncoder()]);

        return $this->data = [
            'campaign' => $raw['campaign'],
            'models' => $serializer->denormalize($raw['models'], SignatureModel::class . '[]'),
        ];
    }
}
