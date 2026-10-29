<?php

namespace App\Services\Actor;

use App\Entity\Actor;
use App\Entity\ActorAssignment;
use App\Entity\User\User;
use App\Enum\ActorActivityAction;
use App\Enum\ActorAssignmentRole;
use App\Repository\ActorAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Nomination / fin de mandat des points focaux et des administrateurs de page.
 * Chaque opération conserve l'historique (ended_at) et alimente le journal d'activité.
 */
class ActorAssignmentManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActorAssignmentRepository $assignmentRepository,
        private readonly ActorActivityLogger $activityLogger,
    ) {
    }

    /**
     * Désigne $user comme point focal de $actor. Le point focal précédent (s'il y en a un) est clôturé.
     * Si $user était administrateur de page de cet acteur, cette affectation est clôturée (le point focal
     * a déjà tous les droits d'un admin de page).
     */
    public function assignFocalPoint(Actor $actor, User $user, ?User $by, ?string $reason = null): ActorAssignment
    {
        $current = $this->assignmentRepository->findActiveFocalPoint($actor);
        if (null !== $current && $current->getUser()?->getId() === $user->getId()) {
            return $current;
        }

        if (null !== $current) {
            $this->closeAssignment($current, $by, 'Remplacé par un nouveau point focal');
        }

        $asAdmin = $this->assignmentRepository->findActive($actor, $user, ActorAssignmentRole::PAGE_ADMIN);
        if (null !== $asAdmin) {
            $this->closeAssignment($asAdmin, $by, 'Promu point focal', ActorActivityAction::PAGE_ADMIN_REMOVED);
        }

        // Clôtures envoyées en base AVANT l'insertion : l'index unique partiel ne tolère
        // pas deux points focaux en cours, même brièvement.
        $this->entityManager->flush();

        $assignment = (new ActorAssignment())
            ->setActor($actor)
            ->setUser($user)
            ->setRole(ActorAssignmentRole::FOCAL_POINT)
            ->setAssignedBy($by);
        $this->entityManager->persist($assignment);

        $this->activityLogger->log($actor, $by ?? $user, ActorActivityAction::FOCAL_POINT_ASSIGNED, null, null, array_filter([
            'assignmentId' => (string) $assignment->getId(),
            'focalPointId' => $user->getId(),
            'focalPointName' => $user->getFullName(),
            'reason' => $reason,
        ]));
        $this->entityManager->flush();

        return $assignment;
    }

    public function addPageAdmin(Actor $actor, User $user, ?User $by): ActorAssignment
    {
        if ($this->assignmentRepository->findActive($actor, $user, ActorAssignmentRole::FOCAL_POINT)) {
            throw new UnprocessableEntityHttpException('Cet utilisateur est déjà le point focal de cet acteur : il dispose déjà de tous les droits d\'administration de la page.');
        }

        $existing = $this->assignmentRepository->findActive($actor, $user, ActorAssignmentRole::PAGE_ADMIN);
        if (null !== $existing) {
            return $existing;
        }

        $assignment = (new ActorAssignment())
            ->setActor($actor)
            ->setUser($user)
            ->setRole(ActorAssignmentRole::PAGE_ADMIN)
            ->setAssignedBy($by);
        $this->entityManager->persist($assignment);

        $this->activityLogger->log($actor, $by, ActorActivityAction::PAGE_ADMIN_ADDED, null, null, [
            'assignmentId' => (string) $assignment->getId(),
            'adminId' => $user->getId(),
            'adminName' => $user->getFullName(),
        ]);
        $this->entityManager->flush();

        return $assignment;
    }

    /** Met fin à une affectation (point focal ou admin de page). */
    public function end(ActorAssignment $assignment, ?User $by, ?string $reason = null): void
    {
        if (!$assignment->isActive()) {
            return;
        }

        $this->closeAssignment($assignment, $by, $reason);
        $this->entityManager->flush();
    }

    private function closeAssignment(ActorAssignment $assignment, ?User $by, ?string $reason, ?ActorActivityAction $action = null): void
    {
        $assignment->end($by, $reason);

        $action ??= ActorAssignmentRole::FOCAL_POINT === $assignment->getRole()
            ? ActorActivityAction::FOCAL_POINT_ENDED
            : ActorActivityAction::PAGE_ADMIN_REMOVED;

        $this->activityLogger->log($assignment->getActor(), $by, $action, null, null, array_filter([
            'assignmentId' => (string) $assignment->getId(),
            'userId' => $assignment->getUser()?->getId(),
            'userName' => $assignment->getUserFullName(),
            'reason' => $reason,
        ]));
    }
}