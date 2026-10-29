<?php

namespace App\Services\Extension\Collection;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Actor;
use App\Entity\ActorAssignment;
use App\Entity\Project;
use App\Entity\Resource;
use App\Entity\User\User;
use App\Model\Enums\UserRoles;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class isValidatedExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addValidated($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addValidated($queryBuilder, $resourceClass, true);
    }

    private function isSupport(string $resourceClass): bool
    {
        return in_array($resourceClass, [Actor::class, Resource::class, Project::class]);
    }

    private function addValidated(QueryBuilder $queryBuilder, string $resourceClass, bool $createdBySupported = false): void
    {
        if (
            !$this->isSupport($resourceClass)
            || $this->security->isGranted(UserRoles::ROLE_ADMIN)
            || ($createdBySupported && $this->security->isGranted(UserRoles::ROLE_EDITOR_RESSOURCES))
        ) {
            return;
        }

        $this->addWhere($queryBuilder, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $user = $this->security->getUser();

        // Les non validés restent visibles de ceux qui gèrent l'acteur concerné (point focal /
        // administrateurs de page) : ils doivent pouvoir retrouver, corriger et valider leurs éléments.
        if ($user instanceof User) {
            $actorCondition = Actor::class === $resourceClass
                ? sprintf('ua.actor = %s', $rootAlias)
                : sprintf('ua.actor = %s.actor', $rootAlias);

            $queryBuilder->andWhere(sprintf(
                '%1$s.isValidated = :isValidated OR EXISTS (SELECT 1 FROM %2$s ua WHERE %3$s AND ua.user = :actorManagerUser AND ua.endedAt IS NULL)',
                $rootAlias,
                ActorAssignment::class,
                $actorCondition
            ));
            $queryBuilder->setParameter('actorManagerUser', $user);
        } else {
            $queryBuilder->andWhere(sprintf('%s.isValidated = :isValidated', $rootAlias));
        }

        $queryBuilder->setParameter('isValidated', true);
    }
}