<?php

namespace App\Services\Actor;

use App\Entity\Actor;
use App\Entity\ActorActivityLog;
use App\Entity\User\User;
use App\Enum\ActorActivityAction;
use App\Enum\ActorChangeTargetType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Écrit dans le journal d'activité d'un acteur. Ne flush pas : l'appelant flush
 * (le log part ainsi dans la même transaction que l'action qu'il décrit).
 */
class ActorActivityLogger
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function log(
        Actor $actor,
        ?User $user,
        ActorActivityAction $action,
        ?ActorChangeTargetType $targetType = null,
        ?string $targetId = null,
        ?array $details = null,
    ): ActorActivityLog {
        $log = (new ActorActivityLog())
            ->setActor($actor)
            ->setUser($user)
            ->setAction($action)
            ->setTargetType($targetType)
            ->setTargetId($targetId)
            ->setDetails($details);

        $this->entityManager->persist($log);

        return $log;
    }
}