<?php

namespace App\Repository;

use App\Entity\Actor;
use App\Entity\ActorAssignment;
use App\Entity\User\User;
use App\Enum\ActorAssignmentRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActorAssignment>
 */
class ActorAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActorAssignment::class);
    }

    public function findActiveFocalPoint(Actor $actor): ?ActorAssignment
    {
        return $this->findOneBy(['actor' => $actor, 'role' => ActorAssignmentRole::FOCAL_POINT, 'endedAt' => null]);
    }

    public function findActive(Actor $actor, User $user, ?ActorAssignmentRole $role = null): ?ActorAssignment
    {
        $criteria = ['actor' => $actor, 'user' => $user, 'endedAt' => null];
        if (null !== $role) {
            $criteria['role'] = $role;

            return $this->findOneBy($criteria);
        }

        // Si, par anomalie, l'utilisateur cumulait deux affectations en cours, le point focal l'emporte.
        foreach ($this->findBy($criteria) as $assignment) {
            if (ActorAssignmentRole::FOCAL_POINT === $assignment->getRole()) {
                return $assignment;
            }
            $found = $assignment;
        }

        return $found ?? null;
    }

    /**
     * @return ActorAssignment[]
     */
    public function findActiveByActor(Actor $actor): array
    {
        return $this->findBy(['actor' => $actor, 'endedAt' => null], ['startedAt' => 'ASC']);
    }

    /**
     * @return ActorAssignment[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->findBy(['user' => $user, 'endedAt' => null], ['startedAt' => 'ASC']);
    }
}