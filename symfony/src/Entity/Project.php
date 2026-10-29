<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\Project\SimilarProjectsAction;
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
use App\Enum\AdministrativeScope;
use App\Enum\BeneficiaryType;
use App\Enum\FinancialStatus;
use App\Enum\ProjectFinancingType;
use App\Enum\Status;
use App\Enum\TechnicalMaturity;
use App\Model\Enums\UserRoles;
use App\Repository\ProjectRepository;
use App\Security\Voter\ActorContentVoter;
use App\Services\State\Processor\ProjectProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Index(columns: ['slug', 'is_validated'], name: 'idx_project_slug_is_validated')]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['slug' => SearchFilterInterface::STRATEGY_EXACT])]
#[ApiResource(
    paginationEnabled: false,
    operations: [
        new GetCollection(
            uriTemplate: '/projects/all',
            normalizationContext: ['groups' => [self::GET_PARTIAL, MediaObject::READ]]
        ),
        new GetCollection(
            uriTemplate: '/projects/{id}/similar',
            controller: SimilarProjectsAction::class,
            normalizationContext: ['groups' => [self::GET_PARTIAL, MediaObject::READ]]
        ),
        new GetCollection(
            uriTemplate: '/projects',
            normalizationContext: ['groups' => [self::GET_FULL, MediaObject::READ, Admin1Boundary::GET_WITH_GEOM, Admin3Boundary::GET_WITH_GEOM]]
        ),
        new Get(
            normalizationContext: ['groups' => [self::GET_FULL, self::GET_PARTIAL, MediaObject::READ, Admin1Boundary::GET_WITH_GEOM, Admin3Boundary::GET_WITH_GEOM]]
        ),
    ]
)]
#[ApiResource(
    operations: [
        // Éditeur de projets, OU point focal / administrateur de page de l'acteur auquel le projet est rattaché.
        // (securityPostDenormalize : l'acteur du projet n'est connu qu'une fois le corps de requête désérialisé)
        new Post(
            securityPostDenormalize: "is_granted('".UserRoles::ROLE_EDITOR_PROJECTS."') or is_granted('".ActorContentVoter::EDIT."', object)",
            processor: ProjectProcessor::class
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN') or (is_granted('".UserRoles::ROLE_EDITOR_PROJECTS."') and object.getCreatedBy() == user) or is_granted('".ActorContentVoter::EDIT."', object)",
            processor: ProjectProcessor::class
        ),
        new Delete(
            security: 'is_granted("ROLE_ADMIN")',
        ),
    ],
    normalizationContext: ['groups' => [self::GET_FULL, self::GET_PARTIAL, MediaObject::READ]],
    denormalizationContext: ['groups' => [self::WRITE]],
)]
class Project
{
    use TimestampableEntity;
    use BlameableEntity;
    use SluggableEntity;
    use LocalizableEntity;
    use ValidateableEntity;
    use CreatorMessageEntity;
    use ThematizedEntity;
    use ODDEntity;
    use BanocEntity;

    public const GET_FULL = 'project:get:full';
    public const GET_PARTIAL = 'project:get:partial';
    public const WRITE = 'project:write';

    public function __construct()
    {
        $this->financingTypes = [];
        $this->images = new ArrayCollection();
        $this->partners = new ArrayCollection();
        $this->resources = new ArrayCollection();
        $this->administrativeScopes = [];
        $this->admin1List = new ArrayCollection();
        $this->admin3List = new ArrayCollection();
        $this->actorsInCharge = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, Actor::ACTOR_READ_ITEM])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE, Actor::ACTOR_READ_ITEM])]
    private ?string $name = null;

    #[ORM\Column(enumType: Status::class)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?Status $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?string $description = null;

    #[ORM\Column(type: 'simple_array', enumType: AdministrativeScope::class)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private array $administrativeScopes = [];

    #[ORM\Column(length: 255)]
    #[Groups([self::GET_FULL, self::WRITE])]
    #[Assert\Length(max: 100)]
    private ?string $focalPointName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    #[Assert\Length(max: 100)]
    private ?string $focalPointPosition = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    #[Assert\Email]
    private ?string $focalPointEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?string $focalPointTel = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?string $focalPointPhoto = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    #[Assert\Url(protocols: ['https'])]
    private ?string $website = null;

    #[ORM\ManyToOne(targetEntity: MediaObject::class)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?MediaObject $logo = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?array $externalImages = null;

    /**
     * @var Collection<int, MediaObject>
     */
    #[ORM\ManyToMany(targetEntity: MediaObject::class, cascade: ['remove'], orphanRemoval: true)]
    #[ApiProperty(types: ['https://schema.org/image'])]
    #[Groups([self::GET_FULL, self::WRITE])]
    private Collection $images;

    /**
     * @var Collection<int, MediaObject>
     */
    #[ORM\ManyToMany(targetEntity: MediaObject::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\JoinTable('projects_partners_media_object')]
    #[ApiProperty(types: ['https://schema.org/image'])]
    #[Groups([self::GET_FULL, self::WRITE])]
    private Collection $partners;

    /**
     * @var Collection<int, ProjectResource>
     */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectResource::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private Collection $resources;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?Actor $actor = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?string $deliverables = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?string $calendar = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups([self::GET_FULL, self::WRITE])]
    private ?array $beneficiaryTypes = null;

    #[ORM\Column(type: 'simple_array', enumType: ProjectFinancingType::class, options: ['default' => ProjectFinancingType::OTHER->value])]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?array $financingTypes;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?string $otherThematic = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?string $otherBeneficiary = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?string $otherFinancingType = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?string $otherActorInCharge = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?string $otherActor = null;

    // --- Nouveaux champs (fichier Excel) ---

    #[ORM\Column(enumType: TechnicalMaturity::class, nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?TechnicalMaturity $technicalMaturity = null;

    #[ORM\Column(enumType: FinancialStatus::class, nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?FinancialStatus $financialStatus = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?float $totalBudget = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private ?float $mobilizedFunds = null;

    /**
     * @var Collection<int, Admin1Boundary>
     */
    #[ORM\ManyToMany(targetEntity: Admin1Boundary::class)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private Collection $admin1List;

    /**
     * @var Collection<int, Admin3Boundary>
     */
    #[ORM\ManyToMany(targetEntity: Admin3Boundary::class)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private Collection $admin3List;

    /**
     * @var Collection<int, Actor>
     */
    #[ORM\ManyToMany(targetEntity: Actor::class)]
    #[Groups([self::GET_FULL, self::GET_PARTIAL, self::WRITE])]
    private Collection $actorsInCharge;

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

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getPartners(): Collection
    {
        return $this->partners;
    }

    public function addPartner(MediaObject $image): static
    {
        if (!$this->partners->contains($image)) {
            $this->partners->add($image);
        }

        return $this;
    }

    public function removePartner(MediaObject $image): static
    {
        $this->partners->removeElement($image);

        return $this;
    }

    /**
     * @return Collection<int, ProjectResource>
     */
    public function getResources(): Collection
    {
        return $this->resources;
    }

    public function addResource(ProjectResource $resource): static
    {
        if (!$this->resources->contains($resource)) {
            $this->resources->add($resource);
            $resource->setProject($this);
        }

        return $this;
    }

    public function removeResource(ProjectResource $resource): static
    {
        if ($this->resources->removeElement($resource)) {
            if ($resource->getProject() === $this) {
                $resource->setProject(null);
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

    /**
     * @return Collection<int, ProjectFinancingType>
     */
    public function getFinancingTypes(): ?array
    {
        return $this->financingTypes;
    }

    public function setFinancingTypes(?array $financingTypes): self
    {
        $this->financingTypes = $financingTypes;

        return $this;
    }

    public function addFinancingType(ProjectFinancingType $financingType): self
    {
        if (!in_array($financingType, $this->financingTypes ?? [], true)) {
            $this->financingTypes[] = $financingType;
        }

        return $this;
    }

    public function removeFinancingType(ProjectFinancingType $financingType): self
    {
        if (($key = array_search($financingType, $this->financingTypes ?? [], true)) !== false) {
            unset($this->financingTypes[$key]);
        }

        return $this;
    }

    public function getFocalPointName(): ?string
    {
        return $this->focalPointName;
    }

    public function setFocalPointName(?string $focalPointName): static
    {
        $this->focalPointName = $focalPointName;

        return $this;
    }

    public function getFocalPointPosition(): ?string
    {
        return $this->focalPointPosition;
    }

    public function setFocalPointPosition(?string $focalPointPosition): static
    {
        $this->focalPointPosition = $focalPointPosition;

        return $this;
    }

    public function getFocalPointEmail(): ?string
    {
        return $this->focalPointEmail;
    }

    public function setFocalPointEmail(?string $focalPointEmail): static
    {
        $this->focalPointEmail = $focalPointEmail;

        return $this;
    }

    public function getFocalPointTel(): ?string
    {
        return $this->focalPointTel;
    }

    public function setFocalPointTel(?string $focalPointTel): static
    {
        $this->focalPointTel = $focalPointTel;

        return $this;
    }

    public function getFocalPointPhoto(): ?string
    {
        return $this->focalPointPhoto;
    }

    public function setFocalPointPhoto(?string $focalPointPhoto): static
    {
        $this->focalPointPhoto = $focalPointPhoto;

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

    public function getExternalImages(): ?array
    {
        return $this->externalImages;
    }

    public function setExternalImages(?array $externalImages): static
    {
        $this->externalImages = $externalImages;

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

    public function getDeliverables(): ?string
    {
        return $this->deliverables;
    }

    public function setDeliverables(?string $deliverables): static
    {
        $this->deliverables = $deliverables;

        return $this;
    }

    public function getCalendar(): ?string
    {
        return $this->calendar;
    }

    public function setCalendar(?string $calendar): static
    {
        $this->calendar = $calendar;

        return $this;
    }

    /**
     * @return BeneficiaryType[]|null
     */
    public function getBeneficiaryTypes(): ?array
    {
        return $this->beneficiaryTypes;
    }

    public function setBeneficiaryTypes(?array $beneficiaryTypes): static
    {
        $this->beneficiaryTypes = $beneficiaryTypes;

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

    public function getOtherBeneficiary(): ?string
    {
        return $this->otherBeneficiary;
    }

    public function setOtherBeneficiary(?string $otherBeneficiary): static
    {
        $this->otherBeneficiary = $otherBeneficiary;

        return $this;
    }

    public function getOtherFinancingType(): ?string
    {
        return $this->otherFinancingType;
    }

    public function setOtherFinancingType(?string $otherFinancingType): static
    {
        $this->otherFinancingType = $otherFinancingType;

        return $this;
    }

    public function getOtherActorInCharge(): ?string
    {
        return $this->otherActorInCharge;
    }

    public function setOtherActorInCharge(?string $otherActorInCharge): static
    {
        $this->otherActorInCharge = $otherActorInCharge;

        return $this;
    }

    public function getOtherActor(): ?string
    {
        return $this->otherActor;
    }

    public function setOtherActor(?string $otherActor): static
    {
        $this->otherActor = $otherActor;

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

    /**
     * @return Collection<int, Actor>
     */
    public function getActorsInCharge(): Collection
    {
        return $this->actorsInCharge;
    }

    public function addActorsInCharge(Actor $actorsInCharge): static
    {
        if (!$this->actorsInCharge->contains($actorsInCharge)) {
            $this->actorsInCharge->add($actorsInCharge);
        }

        return $this;
    }

    public function removeActorsInCharge(Actor $actorsInCharge): static
    {
        $this->actorsInCharge->removeElement($actorsInCharge);

        return $this;
    }

    // --- Nouveaux getters/setters ---

    public function getTechnicalMaturity(): ?TechnicalMaturity
    {
        return $this->technicalMaturity;
    }

    public function setTechnicalMaturity(?TechnicalMaturity $technicalMaturity): static
    {
        $this->technicalMaturity = $technicalMaturity;

        return $this;
    }

    public function getFinancialStatus(): ?FinancialStatus
    {
        return $this->financialStatus;
    }

    public function setFinancialStatus(?FinancialStatus $financialStatus): static
    {
        $this->financialStatus = $financialStatus;

        return $this;
    }

    public function getTotalBudget(): ?float
    {
        return $this->totalBudget;
    }

    public function setTotalBudget(?float $totalBudget): static
    {
        $this->totalBudget = $totalBudget;

        return $this;
    }

    public function getMobilizedFunds(): ?float
    {
        return $this->mobilizedFunds;
    }

    public function setMobilizedFunds(?float $mobilizedFunds): static
    {
        $this->mobilizedFunds = $mobilizedFunds;

        return $this;
    }

    /**
     * Calculé automatiquement : totalBudget - mobilizedFunds. Champ en lecture seule (pas dans WRITE).
     */
    #[Groups([self::GET_FULL, self::GET_PARTIAL])]
    public function getResidualGap(): ?float
    {
        if (null === $this->totalBudget || null === $this->mobilizedFunds) {
            return null;
        }

        return $this->totalBudget - $this->mobilizedFunds;
    }
}