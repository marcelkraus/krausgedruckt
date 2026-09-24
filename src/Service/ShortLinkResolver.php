<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Resolves the slug of a printed short link into the Matomo campaign it is
 * measured under. Every short link leads to the homepage; what differs is
 * what the scan is counted as.
 *
 * A signature print answers under `signature-<slug>` and is counted under the
 * campaign of the current event, with its slug as the keyword.
 */
final class ShortLinkResolver
{
    public const SIGNATURE_PREFIX = 'signature-';

    /**
     * What a slug may consist of – shared by the route and the test that
     * holds every configured slug to it, so nothing printed can answer 404.
     */
    public const SLUG_PATTERN = '[a-z0-9-]+';

    public function __construct(
        private readonly SignatureModelCatalog $signatureModels,
    ) {
    }

    /**
     * @return array{mtm_campaign: string, mtm_kwd: string}|null
     */
    public function resolve(string $slug): ?array
    {
        if (!str_starts_with($slug, self::SIGNATURE_PREFIX)) {
            return null;
        }

        $model = $this->signatureModels->find(substr($slug, \strlen(self::SIGNATURE_PREFIX)));

        return null === $model ? null : [
            'mtm_campaign' => $this->signatureModels->campaign()['slug'],
            'mtm_kwd' => $model->slug,
        ];
    }
}
