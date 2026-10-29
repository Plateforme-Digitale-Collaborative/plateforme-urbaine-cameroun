<?php

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\Resource;
use App\Entity\User\User;
use App\Services\Actor\ActorAccessChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droit d'ajouter / modifier un projet ou une ressource rattaché(e) à un acteur dont on est
 * point focal ou administrateur de page.
 *
 * Pour un administrateur de page, l'ajout / la modification n'est pas public avant validation
 * du point focal (cf. ActorChangeWorkflow) ; ce voter ne fait qu'ouvrir le droit de l'envoyer.
 */
class ActorContentVoter extends Voter
{
    public const EDIT = 'ACTOR_CONTENT_EDIT';

    public function __construct(private readonly ActorAccessChecker $accessChecker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && ($subject instanceof Project || $subject instanceof Resource);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $actor = $subject->getActor();
        if (null === $actor) {
            return false;
        }

        return $this->accessChecker->canContribute($user, $actor);
    }
}