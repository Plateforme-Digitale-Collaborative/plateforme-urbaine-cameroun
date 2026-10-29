<?php

namespace App\Services\State\Processor\Actor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ActorAssignment;
use App\Entity\User\User;
use App\Enum\ActorAssignmentRole;
use App\Security\Voter\ActorVoter;
use App\Services\Actor\ActorAssignmentManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * DELETE /api/actor_assignments/{id} : met fin à l'affectation (suppression douce : la ligne est conservée
 * avec sa date de fin, pour l'historique). Retirer un point focal est réservé aux admins PDC.
 */
class ActorAssignmentEndProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
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

        if (ActorAssignmentRole::FOCAL_POINT === $data->getRole() && !$this->security->isGranted(ActorVoter::MANAGE_FOCAL_POINT, $data->getActor())) {
            throw new AccessDeniedHttpException('Seul un administrateur de la plateforme peut retirer le point focal d\'un acteur.');
        }

        $this->assignmentManager->end($data, $current, 'Retiré par '.$current->getFullName());

        return null;
    }
}