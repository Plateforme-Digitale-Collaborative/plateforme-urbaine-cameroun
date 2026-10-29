<?php

namespace App\Services\State\Processor\Actor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ActorAssignment;
use App\Entity\User\User;
use App\Enum\ActorAssignmentRole;
use App\Repository\User\UserRepository;
use App\Security\Voter\ActorVoter;
use App\Services\Actor\ActorAssignmentManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * POST /api/actor_assignments  { "actor": "/api/actors/{id}", "targetUserId": 12 (ou "userEmail": "..."), "role": "focal_point" | "page_admin" }
 *
 *  - focal_point : réservé aux admins PDC. Remplace le point focal en cours (qui est clôturé, historique conservé).
 *  - page_admin  : admin PDC ou point focal de l'acteur.
 */
class ActorAssignmentProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private UserRepository $userRepository,
        private ActorAssignmentManager $assignmentManager,
    ) {
    }

    /**
     * @param ActorAssignment $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $current = $this->security->getUser();
        if (!$current instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $actor = $data->getActor();
        $role = $data->getRole();
        if (null === $actor || null === $role) {
            throw new UnprocessableEntityHttpException('Les champs « actor » et « role » sont obligatoires.');
        }

        if (null !== $data->getTargetUserId()) {
            $target = $this->userRepository->find($data->getTargetUserId());
        } else {
            $email = $data->getUserEmail();
            if (null === $email || '' === trim($email)) {
                throw new UnprocessableEntityHttpException('Le champ « targetUserId » (ou « userEmail ») est obligatoire.');
            }
            $target = $this->userRepository->findOneBy(['email' => trim($email)]);
        }
        if (!$target instanceof User) {
            throw new UnprocessableEntityHttpException('Utilisateur introuvable.');
        }
        if (!$target->getIsValidated()) {
            throw new UnprocessableEntityHttpException('Le compte de cet utilisateur n\'a pas encore été validé.');
        }

        if (ActorAssignmentRole::FOCAL_POINT === $role) {
            if (!$this->security->isGranted(ActorVoter::MANAGE_FOCAL_POINT, $actor)) {
                throw new AccessDeniedHttpException('Seul un administrateur de la plateforme peut désigner le point focal d\'un acteur.');
            }

            return $this->assignmentManager->assignFocalPoint($actor, $target, $current);
        }

        if (!$this->security->isGranted(ActorVoter::MANAGE_ADMINS, $actor)) {
            throw new AccessDeniedHttpException('Seuls un administrateur de la plateforme ou le point focal de l\'acteur peuvent nommer des administrateurs de page.');
        }

        return $this->assignmentManager->addPageAdmin($actor, $target, $current);
    }
}