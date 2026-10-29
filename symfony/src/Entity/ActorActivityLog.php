<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\User\User;
use App\Enum\ActorActivityAction;
use App\Enum\ActorChangeTargetType;
use App\Repository\ActorActivityLogRepository;
use App\Security\Voter\ActorVoter;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Journal (en ajout seul) des actions menées sur la page d'un acteur : qui a fait quoi, et quand.
 * Alimenté exclusivement par ActorActivityLogger ; exposé en lecture seule.
 */
#[ORM\Entity(repositoryClass: ActorActivityLogRepository::class)]
#[ORM\Table(name: 'actor_activity_log')]
#[ORM\Index(name: 'idx_actor_activity_log_actor_date', columns: ['actor_id', 'created_at'])]
#[ORM\Index(name: 'idx_actor_activity_log_user', columns: ['user_id'])]
#[ApiResource(
    operations: [
        // La visibilité des lignes est restreinte par ActorScopeExtension.
        new GetCollection(security: "is_granted('ROLE_USER')", paginationEnabled: true, paginationItemsPerPage: 50),
        new Get(security: "is_granted('".ActorVoter::VALIDATE."', object.getActor()) or object.getUser() == user"),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
)]
#[ApiFilter(SearchFilter::class, properties: ['actor' => 'exact', 'user' => 'exact', 'action' => 'exact', 'targetType' => 'exact', 'targetId' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt' => 'DESC'])]
class ActorActivityLog
{
    public const GROUP_READ = 'actor_activity_log:read';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups([self::GROUP_READ])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Actor::class)]
    #[ORM\JoinColumn(name: 'actor_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_READ])]
    private ?Actor $actor = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /** Nom de l'utilisateur au moment de l'action. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?string $userLabel = null;

    #[ORM\Column(enumType: ActorActivityAction::class)]
    #[Groups([self::GROUP_READ])]
    private ?ActorActivityAction $action = null;

    #[ORM\Column(nullable: true, enumType: ActorChangeTargetType::class)]
    #[Groups([self::GROUP_READ])]
    private ?ActorChangeTargetType $targetType = null;

    #[ORM\Column(length: 36, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?string $targetId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GROUP_READ])]
    private ?array $details = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTime();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        $this->userLabel = $user?->getFullName();

        return $this;
    }

    #[Groups([self::GROUP_READ])]
    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }

    public function getUserLabel(): ?string
    {
        return $this->userLabel;
    }

    public function getAction(): ?ActorActivityAction
    {
        return $this->action;
    }

    public function setAction(ActorActivityAction $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getTargetType(): ?ActorChangeTargetType
    {
        return $this->targetType;
    }

    public function setTargetType(?ActorChangeTargetType $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): ?string
    {
        return $this->targetId;
    }

    public function setTargetId(?string $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): static
    {
        $this->details = $details;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}