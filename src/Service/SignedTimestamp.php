<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The signed timestamp both hand-rolled forms carry against bots.
 *
 * A form states when it was rendered, and the statement is signed against
 * the application secret – without the signature a bot would simply post a
 * value old enough to look human. Too fast is a machine, too old is a
 * person whose tab sat open, and the two are answered differently.
 */
final class SignedTimestamp
{
    /**
     * Under three seconds nobody has read the form, let alone filled it.
     */
    public const int MINIMUM_AGE = 3;

    /**
     * Two hours: long enough for a page left open over lunch, short enough
     * that a harvested pair is worthless by the time it is used.
     */
    public const int LIFETIME = 7200;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    /**
     * @return array{timestamp: string, signature: string}
     */
    private function issue(): array
    {
        $timestamp = (string) time();

        return ['timestamp' => $timestamp, 'signature' => $this->sign($timestamp)];
    }

    public function state(string $timestamp, string $signature): TimestampState
    {
        if ($timestamp === '' || hash_equals($this->sign($timestamp), $signature) === false) {
            return TimestampState::Invalid;
        }

        $elapsed = time() - (int) $timestamp;

        if ($elapsed < self::MINIMUM_AGE) {
            return TimestampState::TooFast;
        }

        if ($elapsed > self::LIFETIME) {
            return TimestampState::Expired;
        }

        return TimestampState::Valid;
    }

    /**
     * Keeps a pair the visitor already carries when a submission comes back
     * with errors, so a corrected form is not suddenly too fast.
     *
     * @return array{timestamp: string, signature: string}
     */
    public function reissueOrKeep(string $timestamp, string $signature): array
    {
        if ($timestamp !== ''
            && hash_equals($this->sign($timestamp), $signature)
            && time() - (int) $timestamp <= self::LIFETIME
        ) {
            return ['timestamp' => $timestamp, 'signature' => $signature];
        }

        return $this->issue();
    }

    private function sign(string $timestamp): string
    {
        return hash_hmac('sha256', $timestamp, $this->secret);
    }
}
