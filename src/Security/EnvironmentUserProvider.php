<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * The single administrator, taken from the environment instead of from
 * security.yaml.
 *
 * A memory provider spells the user name out as a YAML key, and a key is one
 * of the few places Symfony does not resolve `%env()%`. That left the name in
 * the repository, right next to the path it unlocks — half a set of
 * credentials, handed over for free. Both halves live in `.env.local` now,
 * which is never committed.
 *
 * @implements UserProviderInterface<InMemoryUser>
 */
final class EnvironmentUserProvider implements UserProviderInterface
{
    public function __construct(
        #[Autowire('%env(ADMIN_USERNAME)%')] private readonly string $userName,
        #[Autowire('%env(ADMIN_PASSWORD)%')] private readonly string $passwordHash,
    ) {
        // Intentionally left blank.
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // An unset user name must not turn an empty identifier into a valid
        // one, so the emptiness is checked before the comparison.
        if ($this->userName === '' || hash_equals($this->userName, $identifier) === false) {
            throw new UserNotFoundException();
        }

        return new InMemoryUser($this->userName, $this->passwordHash, ['ROLE_ADMIN']);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if ($user instanceof InMemoryUser === false) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === InMemoryUser::class;
    }
}
