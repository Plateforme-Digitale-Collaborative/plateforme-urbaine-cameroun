<?php

namespace App\Repository;

use App\Entity\Actor;
use App\Entity\ActorChangeRequest;
use App\Entity\User\User;
use App\Enum\ActorChangeOperation;
use App\Enum\ActorChangeStatus;
use App\Enum\ActorChangeTargetType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ActorChangeRequest>
 */
class ActorChangeRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActorChangeRequest::class);
    }

    /**
     * Demande de modification en attente, du même auteur, sur la même cible
     * (on la met à jour plutôt que d'empiler les demandes).
     */
    public function findPendingUpdate(User $user, Actor $actor, ActorChangeTargetType $type, string $targetId): ?ActorChangeRequest
    {
        return $this->findOneBy([
            'requestedBy' => $user,
            'actor' => $actor,
            'targetType' => $type,
            'targetId' => $targetId,
            'operation' => ActorChangeOperation::UPDATE,
            'status' => ActorChangeStatus::PENDING,
        ]);
    }

    /**
     * Statut ACTUEL en base (l'entité hydratée par API Platform porte déjà le statut demandé).
     */
    public function getCurrentStatus(Uuid $id): ?ActorChangeStatus
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.status AS status')
            ->where('r.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        $status = $result['status'] ?? null;

        return $status instanceof ActorChangeStatus ? $status : (null !== $status ? ActorChangeStatus::tryFrom((string) $status) : null);
    }
}