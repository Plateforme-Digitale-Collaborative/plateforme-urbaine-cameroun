<?php

namespace App\Entity\DiverCity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\User\User;
use App\Repository\DiverCity\SpaceAdminRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SpaceAdminRepository::class)]
#[ORM\Table(name: 'space_admin', schema: 'divercity')]
#[ORM\UniqueConstraint(name: 'uniq_space_admin_user_space', columns: ['user_id', 'space_id'])]
#[UniqueEntity(fields: ['user', 'space'], message: 'Cet utilisateur est déjà admin de cet espace.')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("ROLE_ADMIN")'),
        new GetCollection(security: 'is_granted("ROLE_ADMIN")'),
        new Post(security: 'is_granted("ROLE_ADMIN")'),
        new Delete(security: 'is_granted("ROLE_ADMIN")'),
    ],
    normalizationContext: ['groups' => [self::GROUP_READ]],
    denormalizationContext: ['groups' => [self::GROUP_WRITE]],
)]
#[ApiFilter(SearchFilter::class, properties: ['user' => 'exact', 'space' => 'exact'])]
class SpaceAdmin
{
    public const GROUP_READ = 'divercity_space_admin:read';
    public const GROUP_WRITE = 'divercity_space_admin:write';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups([self::GROUP_READ])]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'admins')]
    #[ORM\JoinColumn(name: 'space_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups([self::GROUP_READ, self::GROUP_WRITE])]
    private ?Space $space = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([self::GROUP_READ])]
    private ?\DateTimeInterface $assignedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSpace(): ?Space
    {
        return $this->space;
    }

    public function setSpace(?Space $space): static
    {
        $this->space = $space;

        return $this;
    }

    public function getAssignedAt(): ?\DateTimeInterface
    {
        return $this->assignedAt;
    }
}
