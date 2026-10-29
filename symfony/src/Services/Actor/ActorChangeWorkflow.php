<?php

namespace App\Services\Actor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Actor;
use App\Entity\ActorChangeRequest;
use App\Entity\Project;
use App\Entity\Resource;
use App\Entity\User\User;
use App\Enum\ActorActivityAction;
use App\Enum\ActorAssignmentRole;
use App\Enum\ActorChangeOperation;
use App\Enum\ActorChangeStatus;
use App\Enum\ActorChangeTargetType;
use App\Repository\ActorChangeRequestRepository;
use App\Services\State\Processor\Common\GeoDataProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Cœur du workflow « page d'acteur » :
 *
 *  - admin PDC                       → la modification est appliquée directement ;
 *  - point focal de l'acteur         → appliquée directement (c'est lui qui valide) ;
 *  - administrateur de page          → NON appliquée : enregistrée dans une ActorChangeRequest
 *                                      (en attente), jusqu'à validation par le point focal ;
 *  - tout autre utilisateur          → comportement historique inchangé.
 *
 * Périmètre : l'acteur lui-même, et les projets / ressources qui lui sont rattachés (champ `actor`).
 */
class ActorChangeWorkflow
{
    /** Attribut de requête lu par PendingValidationHeaderListener (en-tête X-Change-Pending). */
    public const REQUEST_ATTRIBUTE_PENDING = '_actor_change_pending';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly ActorAccessChecker $accessChecker,
        private readonly ActorActivityLogger $activityLogger,
        private readonly ActorAssignmentManager $assignmentManager,
        private readonly ActorChangeRequestRepository $requestRepository,
        private readonly SerializerInterface $serializer,
        private readonly GeoDataProcessor $geoDataProcessor,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Enveloppe le traitement habituel d'un Actor / Project / Resource (POST, PATCH, PUT).
     *
     * @param Actor|Project|Resource $data
     * @param callable():mixed       $normalPath traitement habituel (validation, géodonnées, persistance)
     */
    public function handle(object $data, Operation $operation, array $context, callable $normalPath): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $normalPath();
        }

        $isCreate = $operation instanceof Post;

        // Création d'un acteur : l'acteur n'existe pas encore, il n'a donc pas d'affectation.
        // (le point focal est désigné ensuite, cf. onActorCreated)
        if ($data instanceof Actor && $isCreate) {
            return $normalPath();
        }

        $actor = $isCreate ? $this->actorOf($data) : $this->originalActorOf($data);
        if (null === $actor) {
            return $normalPath();
        }

        $targetType = $this->targetTypeOf($data);

        // --- Admin PDC : direct, avec trace dans le journal. ---
        if ($this->accessChecker->isPlatformAdmin()) {
            $result = $normalPath();
            $this->logDirect($actor, $user, $data, $isCreate, $context, $targetType);

            return $result;
        }

        $assignment = $this->accessChecker->getActiveAssignment($user, $actor);
        if (null === $assignment) {
            // Aucun lien avec cet acteur : comportement historique.
            return $normalPath();
        }

        // Déplacer un projet / une ressource vers un autre acteur : réservé au point focal des DEUX acteurs.
        if (!$isCreate && !$data instanceof Actor) {
            $newActor = $data->getActor();
            if (null !== $newActor && !$this->sameActor($newActor, $actor)) {
                if (ActorAssignmentRole::FOCAL_POINT !== $assignment->getRole() || !$this->accessChecker->isFocalPoint($user, $newActor)) {
                    throw new UnprocessableEntityHttpException('Changer l\'acteur de rattachement n\'est possible que pour le point focal des deux acteurs concernés, ou un administrateur de la plateforme.');
                }
            }
        }

        // --- Point focal : direct. ---
        if (ActorAssignmentRole::FOCAL_POINT === $assignment->getRole()) {
            $result = $normalPath();
            if ($isCreate) {
                // Le point focal valide lui-même : l'élément est public tout de suite.
                // (fait APRÈS le traitement habituel : ValidatorProcessor remet isValidated à false pour un non-admin)
                $data->setIsValidated(true);
            }
            $this->logDirect($actor, $user, $data, $isCreate, $context, $targetType);

            return $result;
        }

        // --- Administrateur de page : proposition à valider. ---
        return $isCreate
            ? $this->proposeCreation($data, $actor, $user, $context, $normalPath, $targetType)
            : $this->proposeUpdate($data, $actor, $user, $context, $targetType);
    }

    /**
     * Appelé après la création d'un acteur : si le créateur n'est pas admin PDC, il devient point focal.
     */
    public function onActorCreated(Actor $actor): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->activityLogger->log($actor, $user, ActorActivityAction::ACTOR_CREATED, ActorChangeTargetType::ACTOR, (string) $actor->getId(), [
            'name' => $actor->getName(),
        ]);
        $this->entityManager->flush();

        if (!$this->accessChecker->isPlatformAdmin()) {
            $this->assignmentManager->assignFocalPoint($actor, $user, null, 'Créateur de la page de l\'acteur');
        }
    }

    // ------------------------------------------------------------------
    // Décision du point focal
    // ------------------------------------------------------------------

    public function approve(ActorChangeRequest $request, User $reviewer, ?string $comment): void
    {
        $this->assertPending($request);

        $entity = $this->findTarget($request);
        if (null === $entity) {
            throw new UnprocessableEntityHttpException('L\'élément concerné n\'existe plus : la demande ne peut pas être approuvée.');
        }

        if (ActorChangeOperation::UPDATE === $request->getOperation()) {
            $this->applyPayload($entity, $request);
        }

        // Approuver = rendre public. (Pour un acteur, la validation initiale reste du ressort des admins PDC :
        // le payload ne touche jamais à isValidated.)
        if (!$entity instanceof Actor) {
            $entity->setIsValidated(true);
        }

        $request->markReviewed(ActorChangeStatus::APPROVED, $reviewer, $comment);
        $this->activityLogger->log($request->getActor(), $reviewer, ActorActivityAction::CHANGE_APPROVED, $request->getTargetType(), $request->getTargetId(), $this->requestDetails($request));
        $this->entityManager->flush();
    }

    public function reject(ActorChangeRequest $request, User $reviewer, ?string $comment): void
    {
        $this->assertPending($request);

        if (null === $comment || '' === trim($comment)) {
            throw new UnprocessableEntityHttpException('Un motif est obligatoire en cas de refus.');
        }

        // Rien à annuler : une modification (UPDATE) n'a jamais été appliquée ; une création (CREATE)
        // reste simplement non validée, donc invisible du public.
        $request->markReviewed(ActorChangeStatus::REJECTED, $reviewer, $comment);
        $this->activityLogger->log($request->getActor(), $reviewer, ActorActivityAction::CHANGE_REJECTED, $request->getTargetType(), $request->getTargetId(), $this->requestDetails($request));
        $this->entityManager->flush();
    }

    // ------------------------------------------------------------------
    // Internes
    // ------------------------------------------------------------------

    private function proposeCreation(object $data, Actor $actor, User $user, array $context, callable $normalPath, ActorChangeTargetType $targetType): mixed
    {
        // Jamais public avant validation.
        $data->setIsValidated(false);
        $result = $normalPath();
        $data->setIsValidated(false);

        $request = (new ActorChangeRequest())
            ->setActor($actor)
            ->setTargetType($targetType)
            ->setTargetId((string) $data->getId())
            ->setTargetLabel($data->getName())
            ->setOperation(ActorChangeOperation::CREATE)
            ->setRequestedBy($user);
        $this->entityManager->persist($request);

        $this->activityLogger->log($actor, $user, ActorActivityAction::CHANGE_SUBMITTED, $targetType, (string) $data->getId(), [
            'operation' => ActorChangeOperation::CREATE->value,
            'name' => $data->getName(),
            'requestId' => (string) $request->getId(),
        ]);
        $this->entityManager->flush();

        $this->flagPending($context, $request);

        return $result;
    }

    private function proposeUpdate(object $data, Actor $actor, User $user, array $context, ActorChangeTargetType $targetType): mixed
    {
        $payload = $this->requestPayload($context);

        // Annule en mémoire ce qu'API Platform vient d'appliquer sur l'entité : la modification
        // n'est PAS effective tant qu'elle n'est pas validée.
        $this->entityManager->refresh($data);

        $targetId = (string) $data->getId();
        $request = $this->requestRepository->findPendingUpdate($user, $actor, $targetType, $targetId);
        if (null !== $request) {
            // Même auteur, même cible, déjà en attente : on cumule (les dernières valeurs l'emportent).
            $request->setPayload(array_replace($request->getPayload() ?? [], $payload))->touchRequestedAt();
        } else {
            $request = (new ActorChangeRequest())
                ->setActor($actor)
                ->setTargetType($targetType)
                ->setTargetId($targetId)
                ->setOperation(ActorChangeOperation::UPDATE)
                ->setPayload($payload)
                ->setRequestedBy($user);
            $this->entityManager->persist($request);
        }
        $request->setTargetLabel($data->getName());

        $this->activityLogger->log($actor, $user, ActorActivityAction::CHANGE_SUBMITTED, $targetType, $targetId, [
            'operation' => ActorChangeOperation::UPDATE->value,
            'name' => $data->getName(),
            'fields' => array_keys($payload),
            'requestId' => (string) $request->getId(),
        ]);
        $this->entityManager->flush();

        $this->flagPending($context, $request);

        // Réponse = état actuel (inchangé) de l'élément.
        return $data;
    }

    /**
     * Rejoue, sur l'entité, le corps de requête conservé, avec les mêmes groupes d'écriture
     * que l'opération d'origine.
     */
    private function applyPayload(object $entity, ActorChangeRequest $request): void
    {
        $groups = match (true) {
            $entity instanceof Actor => [Actor::ACTOR_WRITE],
            $entity instanceof Project => [Project::WRITE],
            default => [Resource::WRITE],
        };

        try {
            $this->serializer->deserialize(
                json_encode($request->getPayload() ?? [], JSON_THROW_ON_ERROR),
                $entity::class,
                'json',
                [
                    AbstractNormalizer::OBJECT_TO_POPULATE => $entity,
                    AbstractNormalizer::GROUPS => $groups,
                    'groups' => $groups,
                    'api_allow_update' => true,
                ]
            );

            // Même traitement des géodonnées que pour une modification directe (+ persistance).
            $this->geoDataProcessor->process($entity, new Patch(), [], [], $entity instanceof Resource);
        } catch (\Throwable $e) {
            $this->entityManager->refresh($entity);

            throw new UnprocessableEntityHttpException('La modification proposée ne peut plus être appliquée : '.$e->getMessage(), $e);
        }
    }

    private function findTarget(ActorChangeRequest $request): Actor|Project|Resource|null
    {
        $class = match ($request->getTargetType()) {
            ActorChangeTargetType::ACTOR => Actor::class,
            ActorChangeTargetType::PROJECT => Project::class,
            ActorChangeTargetType::RESOURCE => Resource::class,
            default => throw new \LogicException('Type de cible inconnu.'),
        };

        return $this->entityManager->find($class, $request->getTargetId());
    }

    private function assertPending(ActorChangeRequest $request): void
    {
        $current = $this->requestRepository->getCurrentStatus($request->getId());
        if (ActorChangeStatus::PENDING !== $current) {
            throw new UnprocessableEntityHttpException('Cette demande a déjà été traitée.');
        }
    }

    private function actorOf(object $data): ?Actor
    {
        return $data instanceof Actor ? $data : $data->getActor();
    }

    /** Acteur de rattachement AVANT les modifications de la requête en cours. */
    private function originalActorOf(object $data): ?Actor
    {
        if ($data instanceof Actor) {
            return $data;
        }

        $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($data);
        $actor = $original['actor'] ?? null;

        return $actor instanceof Actor ? $actor : null;
    }

    private function sameActor(Actor $a, Actor $b): bool
    {
        return (string) $a->getId() === (string) $b->getId();
    }

    private function targetTypeOf(object $data): ActorChangeTargetType
    {
        return match (true) {
            $data instanceof Actor => ActorChangeTargetType::ACTOR,
            $data instanceof Project => ActorChangeTargetType::PROJECT,
            default => ActorChangeTargetType::RESOURCE,
        };
    }

    private function logDirect(Actor $actor, User $user, object $data, bool $isCreate, array $context, ActorChangeTargetType $targetType): void
    {
        $details = ['name' => $data->getName()];
        if (!$isCreate) {
            $fields = array_keys($this->decodeBody($this->currentRequest($context)));
            if ([] !== $fields) {
                $details['fields'] = $fields;
            }
        }

        $this->activityLogger->log(
            $actor,
            $user,
            $isCreate ? ActorActivityAction::DIRECT_CREATE : ActorActivityAction::DIRECT_UPDATE,
            $targetType,
            (string) $data->getId(),
            $details
        );
        $this->entityManager->flush();
    }

    private function requestPayload(array $context): array
    {
        $payload = $this->decodeBody($this->currentRequest($context));
        if ([] === $payload) {
            throw new BadRequestHttpException('Corps de requête JSON attendu pour proposer une modification.');
        }

        return $payload;
    }

    private function currentRequest(array $context): ?Request
    {
        $request = $context['request'] ?? null;

        return $request instanceof Request ? $request : $this->requestStack->getCurrentRequest();
    }

    private function decodeBody(?Request $request): array
    {
        if (null === $request) {
            return [];
        }

        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function flagPending(array $context, ActorChangeRequest $request): void
    {
        $this->currentRequest($context)?->attributes->set(self::REQUEST_ATTRIBUTE_PENDING, (string) $request->getId());
    }

    private function requestDetails(ActorChangeRequest $request): array
    {
        return array_filter([
            'requestId' => (string) $request->getId(),
            'operation' => $request->getOperation()?->value,
            'name' => $request->getTargetLabel(),
            'requestedById' => $request->getRequestedById(),
            'requestedByName' => $request->getRequestedByName(),
            'comment' => $request->getReviewComment(),
        ], static fn ($v) => null !== $v);
    }
}