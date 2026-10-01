<?php

namespace App\Entity\Trait;

use App\Entity\Actor;
use App\Entity\Project;
use App\Entity\Resource;
use App\Enum\Thematic;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

trait ThematizedEntity
{
    #[ORM\Column(type: 'simple_array', enumType: Thematic::class, nullable: true)]
    // #[Groups([Actor::ACTOR_READ_COLLECTION, Actor::ACTOR_READ_ITEM, Actor::ACTOR_WRITE, Project::GET_FULL, Project::GET_PARTIAL, Project::WRITE, Resource::GET_FULL, Resource::WRITE])]
    #[Groups([Actor::ACTOR_READ_COLLECTION, Actor::ACTOR_READ_ITEM, Actor::ACTOR_WRITE, Actor::ACTOR_READ_COLLECTION_ALL, Project::GET_FULL, Project::GET_PARTIAL, Project::WRITE, Resource::GET_FULL, Resource::WRITE])]
    private ?array $thematics = [];

    public function getThematics(): ?array
    {
        return $this->thematics;
    }

    public function setThematics(?array $thematics): self
    {
        $this->thematics = $thematics;

        return $this;
    }

    public function addThematic(Thematic $thematic): self
    {
        if (!in_array($thematic, $this->thematics ?? [], true)) {
            $this->thematics[] = $thematic;
        }

        return $this;
    }

    public function removeThematic(Thematic $thematic): self
    {
        if (($key = array_search($thematic, $this->thematics ?? [], true)) !== false) {
            unset($this->thematics[$key]);
        }

        return $this;
    }
}
