<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\File\FileObject;
use App\Entity\File\MediaObject;
use App\Entity\Trait\BanocEntity;
use App\Entity\Trait\BlameableEntity;
use App\Entity\Trait\CreatorMessageEntity;
use App\Entity\Trait\LocalizableEntity;
use App\Entity\Trait\ODDEntity;
use App\Entity\Trait\ThematizedEntity;
use App\Entity\Trait\TimestampableEntity;
use App\Entity\Trait\ValidateableEntity;
use App\Enum\AdministrativeScope;
use App\Enum\ResourceFormat;
use App\Enum\ResourceType;
use App\Model\Enums\UserRoles;
use App\Repository\ResourceRepository;
use App\Security\Voter\ActorContentVoter;
use App\Security\Voter\DiverCity\SpaceScopedVoter;
use App\Services\State\Processor\ResourceProcessor;
use App\Services\State\Provider\DiverCity\EligibleHighlightResourcesProvider;
use App\Services\State\Provider\NearestEventProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ResourceRepository::class)]
#[ApiResource(
    paginationEnabled: false,
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => [self::GET_FULL, MediaObject::READ]],
            parameters: [
                'order[:property]' => new QueryParameter(filter: 'offer.order_filter'),
            ]
        ),
        new Get(
            normalizationContext: ['groups' => [self::GET_FULL, MediaObject::READ]],
        ),

        new GetCollection(
            uriTemplate: '/resources/events/nearest',
            provider: NearestEventProvider::class,
            normalizationContext: ['groups' => [self::GET_FULL, MediaObject::READ]]
        ),
        new GetCollection(
            uriTemplate: '/divercity/resources/eligible-for-highlight',
            security: "is_granted('".SpaceScopedVoter::MANAGE_SPACE."')",
            provider: EligibleHighlightResourcesProvider::class,
            normalizationContext: ['groups' => [self::GET_FULL, MediaObject::READ]]
        ),
    ]
)]
#[ApiResource(
    operations: [
        // Éditeur de ressources, OU point focal / administrateur de page de l'acteur auquel la ressource est rattachée.
        // (securityPostDenormalize : l'acteur de la ressource n'est connu qu'une fois le corps de requête désérialisé)
        new Post(
            securityPostDenormalize: UserRoles::IS_GRANTED_EDITOR_RESSOURCES." or is_granted('".ActorContentVoter::EDIT."', object)",
            processor: ResourceProcessor::class
        ),
        new Patch(
            security: 'is_granted("ROLE_ADMIN") or object.getCreatedBy() == user or is_granted("'.ActorContentVoter::EDIT.'", object)',
            processor: ResourceProcessor::class
        ),
        new Delete(
            security: 'is_granted("ROLE_ADMIN") or object.getCreatedBy() == user',
        ),
    ],
    normalizationContext: ['groups' => [self::GET_FULL, MediaObject::READ, Admin1Boundary::GET_WITH_GEOM, Admin3Boundary::GET_WITH_GEOM]],
    denormalizationContext: ['groups' => [self::WRITE]],
)]
class Resource
{
    use TimestampableEntity;
    use BlameableEntity;
    use ValidateableEntity;
    use LocalizableEntity;
    use CreatorMessageEntity;
    use ThematizedEntity;
    use ODDEntity;
    use BanocEntity;

    public const GET_FULL = 'resource:get:full';
    public const WRITE = 'resource:write';

    public function __construct()
    {
        $this->administrativeScopes = [];
        $this->admin1List = new ArrayCollection();
        $this->admin3List = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[Groups([self::GET_FULL])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups([self::GET_FULL, self::WRITE])]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups([self::GET_FULL, self::WRITE])]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(enumType: ResourceFormat::class)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?ResourceFormat $format = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    #[Assert\Url(protocols: ['https'])]
    private ?string $link = null;

    #[ORM\Column(enumType: ResourceType::class)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?ResourceType $type = null;

    #[ORM\Column(nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\ManyToOne(targetEntity: FileObject::class)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?FileObject $file = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?string $author = null;

    #[ORM\OneToOne(targetEntity: MediaObject::class, cascade: ['remove'], orphanRemoval: true)]
    #[ApiProperty(types: ['https://schema.org/image'])]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?MediaObject $previewImage = null;

    /**
     * Acteur auquel la ressource est rattachée (nullable : les ressources existantes n'en ont pas).
     * Le point focal et les administrateurs de page de cet acteur peuvent la gérer.
     */
    #[ORM\ManyToOne(targetEntity: Actor::class)]
    #[ORM\JoinColumn(name: 'actor_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?Actor $actor = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?string $otherType = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?string $otherThematic = null;

    #[ORM\Column(type: 'simple_array', enumType: AdministrativeScope::class, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private array $administrativeScopes = [];
    /**
     * @var Collection<int, Admin1Boundary>
     */
    #[ORM\ManyToMany(targetEntity: Admin1Boundary::class)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private Collection $admin1List;

    /**
     * @var Collection<int, Admin3Boundary>
     */
    #[ORM\ManyToMany(targetEntity: Admin3Boundary::class)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private Collection $admin3List;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
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

    public function getFile(): ?FileObject
    {
        return $this->file;
    }

    public function setFile(?FileObject $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getFormat(): ?ResourceFormat
    {
        return $this->format;
    }

    public function setFormat(ResourceFormat $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getType(): ?ResourceType
    {
        return $this->type;
    }

    public function setType(ResourceType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        if (!$this->startAt) {
            return null;
        }

        // Retourner en timezone local
        return $this->startAt->setTimezone(new \DateTimeZone('Europe/Paris'));
    }

    public function setStartAt(?\DateTimeImmutable $startAt): static
    {
        if ($startAt) {
            $this->startAt = $startAt->setTimezone(new \DateTimeZone('Europe/Paris'));
        } else {
            $this->startAt = null;
        }

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): static
    {
        if ($endAt) {
            $this->endAt = $endAt->setTimezone(new \DateTimeZone('Europe/Paris'));
        } else {
            $this->endAt = null;
        }

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getPreviewImage(): ?MediaObject
    {
        return $this->previewImage;
    }

    public function setPreviewImage(?MediaObject $previewImage): static
    {
        $this->previewImage = $previewImage;

        return $this;
    }

    public function getOtherType(): ?string
    {
        return $this->otherType;
    }

    public function setOtherType(?string $otherType): static
    {
        $this->otherType = $otherType;

        return $this;
    }

    public function getAdministrativeScopes(): ?array
    {
        return $this->administrativeScopes;
    }

    public function setAdministrativeScopes(?array $administrativeScopes): self
    {
        $this->administrativeScopes = $administrativeScopes ?? [];

        return $this;
    }

    public function addAdministrativeScope(AdministrativeScope $scope): self
    {
        if (!in_array($scope, $this->administrativeScopes ?? [], true)) {
            $this->administrativeScopes[] = $scope;
        }

        return $this;
    }

    public function removeAdministrativeScope(AdministrativeScope $scope): self
    {
        if (($key = array_search($scope, $this->administrativeScopes ?? [], true)) !== false) {
            unset($this->administrativeScopes[$key]);
        }

        return $this;
    }

    /**
     * @return Collection<int, Admin1Boundary>
     */
    public function getAdmin1List(): Collection
    {
        return $this->admin1List;
    }

    public function addAdmin1List(Admin1Boundary $admin1List): static
    {
        if (!$this->admin1List->contains($admin1List)) {
            $this->admin1List->add($admin1List);
        }

        return $this;
    }

    public function removeAdmin1List(Admin1Boundary $admin1List): static
    {
        $this->admin1List->removeElement($admin1List);

        return $this;
    }

    public function getOtherThematic(): ?string
    {
        return $this->otherThematic;
    }

    public function setOtherThematic(?string $otherThematic): static
    {
        $this->otherThematic = $otherThematic;

        return $this;
    }

    /**
     * @return Collection<int, Admin3Boundary>
     */
    public function getAdmin3List(): Collection
    {
        return $this->admin3List;
    }

    public function addAdmin3List(Admin3Boundary $admin3List): static
    {
        if (!$this->admin3List->contains($admin3List)) {
            $this->admin3List->add($admin3List);
        }

        return $this;
    }

    public function removeAdmin3List(Admin3Boundary $admin3List): static
    {
        $this->admin3List->removeElement($admin3List);

        return $this;
    }
}