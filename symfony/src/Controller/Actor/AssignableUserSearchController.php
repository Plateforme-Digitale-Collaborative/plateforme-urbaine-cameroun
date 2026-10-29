<?php

namespace App\Controller\Actor;

use App\Entity\Actor;
use App\Entity\ActorAssignment;
use App\Entity\User\User;
use App\Security\Voter\ActorVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/actor_assignable_users?actor={uuid}&q={texte}
 *
 * Recherche par nom (prénom / nom) des utilisateurs validés pouvant être affectés à la page d'un acteur.
 * Réservé aux admins PDC et au point focal de l'acteur. Les utilisateurs déjà affectés (en cours) sont exclus.
 * Seuls l'identifiant et le nom sont renvoyés (pas d'e-mail).
 */
class AssignableUserSearchController extends AbstractController
{
    private const MIN_LENGTH = 2;
    private const LIMIT = 15;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/api/actor_assignable_users', name: 'api_actor_assignable_users', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $actor = $this->entityManager->getRepository(Actor::class)->find((string) $request->query->get('actor'));
        if (!$actor instanceof Actor) {
            throw new NotFoundHttpException('Acteur introuvable.');
        }
        $this->denyAccessUnlessGranted(ActorVoter::MANAGE_ADMINS, $actor);

        $words = preg_split('/\s+/', trim((string) $request->query->get('q')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (0 === count($words) || mb_strlen(implode('', $words)) < self::MIN_LENGTH) {
            return new JsonResponse([]);
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.isValidated = true')
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT 1 FROM %s a WHERE a.actor = :actor AND a.user = u AND a.endedAt IS NULL)',
                ActorAssignment::class
            ))
            ->setParameter('actor', $actor)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->setMaxResults(self::LIMIT);

        foreach ($words as $i => $word) {
            $qb->andWhere(sprintf('LOWER(u.firstName) LIKE :w%1$d OR LOWER(u.lastName) LIKE :w%1$d', $i))
                ->setParameter('w'.$i, '%'.addcslashes(mb_strtolower($word), '%_\\').'%');
        }

        return new JsonResponse(array_map(static fn (User $u) => [
            'id' => $u->getId(),
            'fullName' => $u->getFullName(),
        ], $qb->getQuery()->getResult()));
    }
}