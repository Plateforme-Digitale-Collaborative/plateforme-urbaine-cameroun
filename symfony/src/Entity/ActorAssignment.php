<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\User\User;
use App\Enum\ActorAssignmentRole;
use App\Repository\ActorAssignmentRepository;
use App\Security\Voter\ActorVoter;
use App\Services\State\Processor\Actor\ActorAssignmentEndProcessor;
use App\Services\State\Processor\Actor\ActorAssignmentProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Affectation d'un utilisateur à la page d'un acteur (point focal ou administrateur de page),
 * sur une période : startedAt → endedAt (null = affectation en cours).
 *
 * Les lignes ne sont jamais supprimées : mettre fin à une affectation renseigne endedAt,
 * ce qui conserve l'historique « qui était point focal / admin de quel acteur, et quand ».
 */
#[ORM\Entity(repositoryClass: ActorAssignmentRepository::class)]
#[ORM\Table(name: 'actor_assignment')]
#[ORM\Index(name: 'idx_actor_assignment_actor', columns: ['actor_id'])]
#[ORM\Index(name: 'idx_actor_assignment_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_actor_assignment_assigned_by', columns: ['assigned_by_id'])]
#[ORM\Index(name: 'idx_actor_assignment_ended_by', columns: ['ended_by_id'])]
// Un seul point focal en cours par acteur.
#[ORM\UniqueConstraint(
    name: 'uniq_actor_active_focal_point',
    columns: ['actor_id'],
    options: ['where' => "(role = 'focal_point' AND ended_at IS NULL)"]
)]
// Pas de doublon d'affectation en cours (même acteur, même utilisateur, même rôle).
#[ORM\UniqueConstraint(
    name: 'uniq_actor_active_assignment',
    columns: ['actor_id', 'user_id', 'role'],
    options: ['where' => '(ended_at IS NULL)']
)]
#[ApiResource(
    operations: [
        // La visibilité des lignes est restreinte par ActorScopeExtension.
        new GetCollection(security: "is_granted('ROLE_USER')", paginationEnabled: false),
        new Get(security: "is_granted('ROLE_ADMIN') or object.getUser() == user or is_granted('".ActorVoter::MANAGE_ADMINS."', object.getActor())"),
        // Les droits fins (admin PDC pour le point focal, point focal pour les admins de page)
        // sont contrôlés dans ActorAssignmentProcessor.
        new Post(security: "is_granted('ROLE_USER')", processor: ActorAssignmentProcessor::class),
        // « Supprimer » = mettre fin à l'affectation (suppression douce, historique conservé).
        new Delete(security: "is_granted('ROLE_ADMIN') or is_granted('".ActorVoter::MANAGE_ADMINS."', object.getActor())", processor: ActorAssignmentEndProcessor::class),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]],
)]
#[ApiFilter(SearchFilter::class, properties: ['actor' => 'exact', 'user' => 'exact', 'role' => 'exact'])]
// ?exists[endedAt]=false → uniquement les affectations en cours
#[ApiFilter(ExistsFilter::class, properties: ['endedAt'])]
#[ApiFilter(OrderFilter::class, properties: ['startedAt' => 'DESC', 'endedAt'])]
class ActorAssignment
{
    public const GROUP_READ = 'actor_assignment:read';
    public const GROUP_WRITE = 'actor_assignment:write';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups([self::GROUP_READ])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Actor::class)]
    #[ORM\JoinColumn(name: 'actor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Assert\NotNull]
    private ?Actor $actor = null;

    // Pas de groupe : l'utilisateur est exposé via userId / userFullName / userEmail (cf. getters),
    // et désigné à l'écriture par userEmail.
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /** Nom de l'utilisateur au moment de l'affectation (l'historique survit à la suppression du compte). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userLabel = null;

    /** Champ non persisté : e-mail de l'utilisateur à affecter (écriture), ou e-mail actuel (lecture). */
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Assert\Email]
    private ?string $userEmail = null;

    /** Champ non persisté : identifiant de l'utilisateur à affecter (écriture), choisi via la recherche par nom. */
    #[Groups([self::GROUP_WRITE])]
    private ?int $targetUserId = null;

    #[ORM\Column(enumType: ActorAssignmentRole::class)]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    #[Assert\NotNull]
    private ?ActorAssignmentRole $role = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeInterface $endedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?string $endReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assigned_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'ended_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $endedBy = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->startedAt = new \DateTime();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getActor(): ?Actor
    {
        return $this->actor;
    }

    public function setActor(?Actor $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        if (null !== $user) {
            $this->userLabel = $user->getFullName();
        }

        return $this;
    }

    public function getUserLabel(): ?string
    {
        return $this->userLabel;
    }

    #[Groups([self::GROUP_READ])]
    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }

    #[Groups([self::GROUP_READ])]
    public function getUserFullName(): ?string
    {
        return $this->user?->getFullName() ?? $this->userLabel;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail ?? $this->user?->getEmail();
    }

    public function getTargetUserId(): ?int
    {
        return $this->targetUserId;
    }

    public function setTargetUserId(?int $targetUserId): static
    {
        $this->targetUserId = $targetUserId;

        return $this;
    }

    public function setUserEmail(?string $userEmail): static
    {
        $this->userEmail = $userEmail;

        return $this;
    }

    public function getRole(): ?ActorAssignmentRole
    {
        return $this->role;
    }

    public function setRole(?ActorAssignmentRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?\DateTimeInterface
    {
        return $this->endedAt;
    }

    public function isActive(): bool
    {
        return null === $this->endedAt;
    }

    public function getEndReason(): ?string
    {
        return $this->endReason;
    }

    public function getAssignedBy(): ?User
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?User $assignedBy): static
    {
        $this->assignedBy = $assignedBy;

        return $this;
    }

    public function getEndedBy(): ?User
    {
        return $this->endedBy;
    }

    public function end(?User $by, ?string $reason = null): static
    {
        $this->endedAt = new \DateTime();
        $this->endedBy = $by;
        $this->endReason = $reason;

        return $this;
    }
}