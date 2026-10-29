<?php

namespace App\Services\Extension\Collection;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\ActorActivityLog;
use App\Entity\ActorAssignment;
use App\Entity\ActorChangeRequest;
use App\Entity\User\User;
use App\Enum\ActorAssignmentRole;
use App\Model\Enums\UserRoles;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restreint les collections ActorAssignment / ActorChangeRequest / ActorActivityLog :
 *
 *  - admin PDC            : tout ;
 *  - point focal          : tout ce qui concerne les acteurs dont il est point focal en cours ;
 *  - administrateur de page : uniquement ses propres lignes (ses affectations, ses demandes, ses actions).
 */
final readonly class ActorScopeExtension implements QueryCollectionExtensionInterface
{
    /** Propriété « utilisateur » propre à chaque ressource. */
    private const OWNER_FIELD = [
        ActorAssignment::class => 'user',
        ActorChangeRequest::class => 'requestedBy',
        ActorActivityLog::class => 'user',
    ];

    public function __construct(private Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!isset(self::OWNER_FIELD[$resourceClass]) || $this->security->isGranted(UserRoles::ROLE_ADMIN)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        // Équipe d'un acteur : tout membre (point focal ou admin de page) voit les affectations en cours
        // de ses acteurs, pour savoir qui est point focal.
        $teamVisibility = ActorAssignment::class === $resourceClass
            ? sprintf(' OR EXISTS (SELECT 1 FROM %2$s tm WHERE tm.actor = %1$s.actor AND tm.user = :actorScopeUser AND tm.endedAt IS NULL AND %1$s.endedAt IS NULL)', $rootAlias, ActorAssignment::class)
            : '';

        $queryBuilder
            ->andWhere(sprintf(
                '%1$s.%2$s = :actorScopeUser OR EXISTS (SELECT 1 FROM %3$s fp WHERE fp.actor = %1$s.actor AND fp.user = :actorScopeUser AND fp.role = :actorScopeFocalRole AND fp.endedAt IS NULL)',
                $rootAlias,
                self::OWNER_FIELD[$resourceClass],
                ActorAssignment::class
            ).$teamVisibility)
            ->setParameter('actorScopeUser', $user)
            ->setParameter('actorScopeFocalRole', ActorAssignmentRole::FOCAL_POINT->value);
    }
}