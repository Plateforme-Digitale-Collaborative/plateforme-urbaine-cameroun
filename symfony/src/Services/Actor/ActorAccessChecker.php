<?php

namespace App\Services\Actor;

use App\Entity\Actor;
use App\Entity\ActorAssignment;
use App\Entity\User\User;
use App\Enum\ActorAssignmentRole;
use App\Model\Enums\UserRoles;
use App\Repository\ActorAssignmentRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Point unique pour répondre à : « cet utilisateur est-il point focal / admin de page de cet acteur ? ».
 */
class ActorAccessChecker
{
    public function __construct(
        private readonly Security $security,
        private readonly ActorAssignmentRepository $assignmentRepository,
    ) {
    }

    public function isPlatformAdmin(): bool
    {
        return $this->security->isGranted(UserRoles::ROLE_ADMIN);
    }

    public function getActiveAssignment(User $user, Actor $actor): ?ActorAssignment
    {
        if (null === $actor->getId()) {
            return null;
        }

        return $this->assignmentRepository->findActive($actor, $user);
    }

    public function isFocalPoint(User $user, Actor $actor): bool
    {
        return ActorAssignmentRole::FOCAL_POINT === $this->getActiveAssignment($user, $actor)?->getRole();
    }

    public function isPageAdmin(User $user, Actor $actor): bool
    {
        return ActorAssignmentRole::PAGE_ADMIN === $this->getActiveAssignment($user, $actor)?->getRole();
    }

    /** Point focal OU administrateur de page : peut agir sur la page (la validation dépend ensuite du rôle). */
    public function canContribute(User $user, Actor $actor): bool
    {
        return null !== $this->getActiveAssignment($user, $actor);
    }

    /** Peut valider les modifications / nommer les admins de page : admin PDC ou point focal en cours. */
    public function canValidate(User $user, Actor $actor): bool
    {
        return $this->isPlatformAdmin() || $this->isFocalPoint($user, $actor);
    }
}