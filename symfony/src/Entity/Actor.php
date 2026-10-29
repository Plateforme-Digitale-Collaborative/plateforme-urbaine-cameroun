<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\File\MediaObject;
use App\Entity\Trait\BanocEntity;
use App\Entity\Trait\BlameableEntity;
use App\Entity\Trait\CreatorMessageEntity;
use App\Entity\Trait\LocalizableEntity;
use App\Entity\Trait\ODDEntity;
use App\Entity\Trait\SluggableEntity;
use App\Entity\Trait\ThematizedEntity;
use App\Entity\Trait\TimestampableEntity;
use App\Entity\Trait\ValidateableEntity;
use App\Entity\User\User;
use App\Enum\ActorCategory;
use App\Enum\AdministrativeScope;
use App\Model\Enums\UserRoles;
use App\Repository\ActorRepository;
use App\Security\Voter\ActorVoter;
use App\Services\State\Processor\ActorProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActorRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => [self::ACTOR_READ_ITEM, MediaObject::READ, Admin1Boundary::GET_WITH_GEOM, Admin3Boundary::GET_WITH_GEOM]],
    denormalizationContext: ['groups' => [self::ACTOR_WRITE, MediaObject::READ]],
    operations: [
        new GetCollection(
            paginationEnabled: false,
            normalizationContext: ['groups' => [self::ACTOR_READ_COLLECTION, MediaObject::READ]]
        ),
        new GetCollection(
            uriTemplate: '/actors/all',
            paginationEnabled: false,
            normalizationContext: ['groups' => [self::ACTOR_READ_COLLECTION_ALL]]
        ),
        new Get(),
        new Post(
            processor: ActorProcessor::class,
            security: "is_granted('".UserRoles::ROLE_EDITOR_ACTORS."')"
        ),
        new Patch(
            processor: ActorProcessor::class,
            security: 'is_granted("'.ActorVoter::EDIT.'", object)'
        ),
        new Put(
            processor: ActorProcessor::class,
            security: 'is_granted("'.ActorVoter::EDIT.'", object)'
        ),
        new Delete(
            security: 'is_granted("ROLE_ADMIN")'
        ),
    ],
)]
class Actor
{
    use TimestampableEntity;
    use SluggableEntity;
    use LocalizableEntity;
    use BlameableEntity;
    use ValidateableEntity;
    use CreatorMessageEntity;
    use ThematizedEntity;
    use ODDEntity;
    use BanocEntity;

    public const ACTOR_READ_COLLECTION = 'actor:read_collection';
    public const ACTOR_READ_COLLECTION_ALL = 'actor:read_collection:all';
    public const ACTOR_READ_ITEM = 'actor:read_item';
    public const ACTOR_WRITE = 'actor:write';

    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->administrativeScopes = [];
        $this->images = new ArrayCollection();
        $this->admin1List = new ArrayCollection();
        $this->admin3List = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_READ_COLLECTION_ALL])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_COLLECTION_ALL, self::ACTOR_READ_ITEM, self::ACTOR_WRITE, Project::GET_PARTIAL, Project::GET_FULL, Resource::GET_FULL])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_WRITE, Project::GET_FULL])]
    private ?string $acronym = null;

    #[ORM\Column(enumType: ActorCategory::class)]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_WRITE, Project::GET_FULL])]
    private ?ActorCategory $category = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?string $officeName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?string $officeAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?string $contactName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?string $contactPosition = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    #[Assert\Email]
    private ?string $email = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'actor')]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private Collection $projects;

    #[ORM\Column(type: 'simple_array', enumType: AdministrativeScope::class)]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private array $administrativeScopes = [];

    #[ORM\OneToOne(targetEntity: MediaObject::class, cascade: ['remove'], orphanRemoval: true)]
    #[ApiProperty(types: ['https://schema.org/image'])]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?MediaObject $logo = null;

    /**
     * @var Collection<int, MediaObject>
     */
    #[ORM\ManyToMany(targetEntity: MediaObject::class, cascade: ['remove'], orphanRemoval: true)]
    #[ApiProperty(types: ['https://schema.org/image'])]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private Collection $images;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?array $externalImages = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?string $otherCategory = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::ACTOR_READ_COLLECTION, self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private ?string $otherThematic = null;
    /**
     * @var Collection<int, Admin1Boundary>
     */
    #[ORM\ManyToMany(targetEntity: Admin1Boundary::class)]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private Collection $admin1List;

    /**
     * @var Collection<int, Admin3Boundary>
     */
    #[ORM\ManyToMany(targetEntity: Admin3Boundary::class)]
    #[Groups([self::ACTOR_READ_ITEM, self::ACTOR_WRITE])]
    private Collection $admin3List;

    // Override Traits groups

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    // #[Groups([Actor::ACTOR_WRITE, Actor::ACTOR_READ_ITEM, Actor::ACTOR_READ_COLLECTION])]
    #[Groups([Actor::ACTOR_WRITE, Actor::ACTOR_READ_ITEM, Actor::ACTOR_READ_COLLECTION, Actor::ACTOR_READ_COLLECTION_ALL])]
    private ?GeoData $geoData = null;

    #[ORM\Column]
    #[Groups([Actor::ACTOR_READ_ITEM, Actor::ACTOR_READ_COLLECTION])]
    private ?bool $isValidated = false;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([Actor::ACTOR_READ_ITEM, Actor::ACTOR_READ_COLLECTION])]
    protected ?\DateTimeInterface $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups([Actor::ACTOR_READ_ITEM, Actor::ACTOR_READ_COLLECTION])]
    protected ?\DateTimeInterface $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL', name: 'created_by')]
    #[Gedmo\Blameable(on: 'create')]
    #[Groups([Actor::ACTOR_READ_ITEM])]
    protected ?User $createdBy;

    // End traits groups

    public function getId(): ?Uuid
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

    public function getAcronym(): ?string
    {
        return $this->acronym;
    }

    public function setAcronym(string $acronym): static
    {
        $this->acronym = strtoupper($acronym);

        return $this;
    }

    public function getCategory(): ?ActorCategory
    {
        return $this->category;
    }

    public function setCategory(ActorCategory $category): static
    {
        $this->category = $category;

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

    public function getOfficeName(): ?string
    {
        return $this->officeName;
    }

    public function setOfficeName(?string $officeName): static
    {
        $this->officeName = $officeName;

        return $this;
    }

    public function getOfficeAddress(): ?string
    {
        return $this->officeAddress;
    }

    public function setOfficeAddress(?string $officeAddress): static
    {
        $this->officeAddress = $officeAddress;

        return $this;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(?string $contactName): static
    {
        $this->contactName = $contactName;

        return $this;
    }

    public function getContactPosition(): ?string
    {
        return $this->contactPosition;
    }

    public function setContactPosition(?string $contactPosition): static
    {
        $this->contactPosition = $contactPosition;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setActor($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getActor() === $this) {
                $project->setActor(null);
            }
        }

        return $this;
    }

    public function getAdministrativeScopes(): ?array
    {
        return $this->administrativeScopes;
    }

    public function setAdministrativeScopes(?array $administrativeScopes): self
    {
        $this->administrativeScopes = $administrativeScopes;

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

    public function getLogo(): ?MediaObject
    {
        return $this->logo;
    }

    public function setLogo(?MediaObject $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * @return Collection<int, MediaObject>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(MediaObject $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
        }

        return $this;
    }

    public function removeImage(MediaObject $image): static
    {
        $this->images->removeElement($image);

        return $this;
    }

    public function getExternalImages(): ?array
    {
        return $this->externalImages;
    }

    public function setExternalImages(?array $externalImages): static
    {
        $this->externalImages = $externalImages;

        return $this;
    }

    public function getOtherCategory(): ?string
    {
        return $this->otherCategory;
    }

    public function setOtherCategory(?string $otherCategory): static
    {
        $this->otherCategory = $otherCategory;

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