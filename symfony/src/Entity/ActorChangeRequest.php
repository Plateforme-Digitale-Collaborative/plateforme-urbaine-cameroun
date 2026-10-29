<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Entity\User\User;
use App\Enum\ActorChangeOperation;
use App\Enum\ActorChangeStatus;
use App\Enum\ActorChangeTargetType;
use App\Repository\ActorChangeRequestRepository;
use App\Security\Voter\ActorVoter;
use App\Services\State\Processor\Actor\ActorChangeRequestReviewProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Modification soumise par un administrateur de page, en attente de validation par le point focal.
 *
 * - CREATE : le projet / la ressource existe déjà en base mais reste non validé (donc invisible du public) ;
 *            la validation le rend public.
 * - UPDATE : la modification n'est PAS appliquée à l'entité tant qu'elle n'est pas validée ;
 *            le corps de la requête (JSON) est conservé dans `payload` et rejoué à l'approbation.
 *
 * Les demandes traitées (approuvées / refusées) sont conservées : c'est l'historique des validations.
 */
#[ORM\Entity(repositoryClass: ActorChangeRequestRepository::class)]
#[ORM\Table(name: 'actor_change_request')]
#[ORM\Index(name: 'idx_actor_change_request_actor_status', columns: ['actor_id', 'status'])]
#[ORM\Index(name: 'idx_actor_change_request_target', columns: ['target_type', 'target_id'])]
#[ORM\Index(name: 'idx_actor_change_request_requested_by', columns: ['requested_by_id'])]
#[ORM\Index(name: 'idx_actor_change_request_reviewed_by', columns: ['reviewed_by_id'])]
#[ApiResource(
    operations: [
        // La visibilité des lignes est restreinte par ActorScopeExtension.
        new GetCollection(security: "is_granted('ROLE_USER')", paginationEnabled: false),
        new Get(security: "is_granted('".ActorVoter::VALIDATE."', object.getActor()) or object.getRequestedBy() == user"),
        // Décision du point focal (ou d'un admin PDC) : status = approved | rejected (+ reviewComment).
        new Patch(
            uriTemplate: '/actor_change_requests/{id}/review',
            security: "is_granted('".ActorVoter::VALIDATE."', object.getActor())",
            denormalizationContext: ['groups' => [self::GROUP_REVIEW]],
            processor: ActorChangeRequestReviewProcessor::class
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'actor' => 'exact',
    'status' => 'exact',
    'targetType' => 'exact',
    'targetId' => 'exact',
    'operation' => 'exact',
    'requestedBy' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['requestedAt' => 'DESC', 'reviewedAt'])]
class ActorChangeRequest
{
    public const GROUP_READ = 'actor_change_request:read';
    public const GROUP_REVIEW = 'actor_change_request:review';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups([self::GROUP_READ])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Actor::class)]
    #[ORM\JoinColumn(name: 'actor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_READ])]
    private ?Actor $actor = null;

    #[ORM\Column(enumType: ActorChangeTargetType::class)]
    #[Groups([self::GROUP_READ])]
    private ?ActorChangeTargetType $targetType = null;

    /** Identifiant (UUID) de l'acteur / du projet / de la ressource concerné(e). */
    #[ORM\Column(length: 36)]
    #[Groups([self::GROUP_READ])]
    private ?string $targetId = null;

    /** Nom de la cible au moment de la demande (affichage dans la liste de validation). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?string $targetLabel = null;

    #[ORM\Column(enumType: ActorChangeOperation::class)]
    #[Groups([self::GROUP_READ])]
    private ?ActorChangeOperation $operation = null;

    /** Corps JSON de la requête de modification (UPDATE uniquement). */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?array $payload = null;

    #[ORM\Column(enumType: ActorChangeStatus::class)]
    #[Groups([self::GROUP_READ, self::GROUP_REVIEW])]
    private ActorChangeStatus $status = ActorChangeStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'requested_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $requestedBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $requestedByLabel = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeInterface $requestedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reviewed_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reviewedByLabel = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeInterface $reviewedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([self::GROUP_READ, self::GROUP_REVIEW])]
    private ?string $reviewComment = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->requestedAt = new \DateTime();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getActor(): ?Actor
    {
        return $this->actor;
    }

    public function setActor(Actor $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getTargetType(): ?ActorChangeTargetType
    {
        return $this->targetType;
    }

    public function setTargetType(ActorChangeTargetType $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): ?string
    {
        return $this->targetId;
    }

    public function setTargetId(string $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getTargetLabel(): ?string
    {
        return $this->targetLabel;
    }

    public function setTargetLabel(?string $targetLabel): static
    {
        $this->targetLabel = $targetLabel;

        return $this;
    }

    public function getOperation(): ?ActorChangeOperation
    {
        return $this->operation;
    }

    public function setOperation(ActorChangeOperation $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getStatus(): ActorChangeStatus
    {
        return $this->status;
    }

    public function setStatus(ActorChangeStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return ActorChangeStatus::PENDING === $this->status;
    }

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $requestedBy): static
    {
        $this->requestedBy = $requestedBy;
        $this->requestedByLabel = $requestedBy?->getFullName();

        return $this;
    }

    #[Groups([self::GROUP_READ])]
    public function getRequestedById(): ?int
    {
        return $this->requestedBy?->getId();
    }

    #[Groups([self::GROUP_READ])]
    public function getRequestedByName(): ?string
    {
        return $this->requestedBy?->getFullName() ?? $this->requestedByLabel;
    }

    public function getRequestedAt(): ?\DateTimeInterface
    {
        return $this->requestedAt;
    }

    public function touchRequestedAt(): static
    {
        $this->requestedAt = new \DateTime();

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    #[Groups([self::GROUP_READ])]
    public function getReviewedByName(): ?string
    {
        return $this->reviewedBy?->getFullName() ?? $this->reviewedByLabel;
    }

    public function getReviewedAt(): ?\DateTimeInterface
    {
        return $this->reviewedAt;
    }

    public function getReviewComment(): ?string
    {
        return $this->reviewComment;
    }

    public function setReviewComment(?string $reviewComment): static
    {
        $this->reviewComment = $reviewComment;

        return $this;
    }

    public function markReviewed(ActorChangeStatus $status, User $reviewer, ?string $comment): static
    {
        $this->status = $status;
        $this->reviewedBy = $reviewer;
        $this->reviewedByLabel = $reviewer->getFullName();
        $this->reviewedAt = new \DateTime();
        $this->reviewComment = $comment;

        return $this;
    }
}