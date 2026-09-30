<?php

namespace App\Repository\DiverCity;

use App\Entity\DiverCity\Space;
use App\Entity\DiverCity\SpaceAdmin;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpaceAdmin>
 */
class SpaceAdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpaceAdmin::class);
    }

    /**
     * Vérifie si un utilisateur est administrateur d'un espace donné.
     */
    public function isAdminOf(User $user, Space $space): bool
    {
        return null !== $this->findOneBy(['user' => $user, 'space' => $space]);
    }

    /**
     * Renvoie la liste des espaces qu'administre un utilisateur donné
     * (utile pour scoper des listes de réservations, de périodes bloquées, etc.).
     *
     * @return Space[]
     */
    public function findSpacesAdministeredBy(User $user): array
    {
        $spaceAdmins = $this->findBy(['user' => $user]);

        return array_map(fn (SpaceAdmin $spaceAdmin) => $spaceAdmin->getSpace(), $spaceAdmins);
    }

    /**
     * Renvoie la liste des utilisateurs administrateurs d'un espace donné.
     *
     * @return User[]
     */
    public function findAdminsOfSpace(Space $space): array
    {
        $spaceAdmins = $this->findBy(['space' => $space]);

        return array_map(fn (SpaceAdmin $spaceAdmin) => $spaceAdmin->getUser(), $spaceAdmins);
    }

    public function isAdminOfAnySpace(User $user): bool
    {
        return $this->count(['user' => $user]) > 0;
    }
}
