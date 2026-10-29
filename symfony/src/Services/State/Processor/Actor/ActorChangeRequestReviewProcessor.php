<?php

namespace App\Services\State\Processor\Actor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ActorChangeRequest;
use App\Entity\User\User;
use App\Enum\ActorChangeStatus;
use App\Services\Actor\ActorChangeWorkflow;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * PATCH /api/actor_change_requests/{id}/review  { "status": "approved" | "rejected", "reviewComment": "..." }
 *
 * Décision du point focal (ou d'un admin PDC) sur une demande en attente. Le refus exige un motif.
 */
class ActorChangeRequestReviewProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ActorChangeWorkflow $workflow,
    ) {
    }

    /**
     * @param ActorChangeRequest $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $reviewer = $this->security->getUser();
        if (!$reviewer instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // $data porte déjà la décision demandée (status / reviewComment) ; le workflow revérifie
        // en base que la demande est toujours en attente avant de l'appliquer.
        $decision = $data->getStatus();
        $comment = $data->getReviewComment();

        switch ($decision) {
            case ActorChangeStatus::APPROVED:
                $this->workflow->approve($data, $reviewer, $comment);
                break;
            case ActorChangeStatus::REJECTED:
                $this->workflow->reject($data, $reviewer, $comment);
                break;
            default:
                throw new UnprocessableEntityHttpException('Le statut doit être « approved » ou « rejected ».');
        }

        return $data;
    }
}