<?php

namespace App\Security\Voter;

use App\Entity\Actor;
use App\Entity\User\User;
use App\Model\Enums\UserRoles;
use App\Services\Actor\ActorAccessChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur la page d'un acteur.
 *
 *  - EDIT                : modifier l'acteur (admin PDC, point focal ou admin de page).
 *                          Pour un admin de page, la modification passe par la validation du point focal
 *                          (cf. ActorChangeWorkflow) ; le droit d'ENVOYER la modification est accordé ici.
 *  - VALIDATE            : valider les modifications, consulter l'historique d'activité (admin PDC ou point focal).
 *  - MANAGE_ADMINS       : nommer / retirer les administrateurs de page (admin PDC ou point focal).
 *  - MANAGE_FOCAL_POINT  : désigner / remplacer le point focal (admin PDC uniquement).
 */
class ActorVoter extends Voter
{
    public const EDIT = 'edit';
    public const CREATE = 'create';
    public const VALIDATE = 'ACTOR_VALIDATE';
    public const MANAGE_ADMINS = 'ACTOR_MANAGE_ADMINS';
    public const MANAGE_FOCAL_POINT = 'ACTOR_MANAGE_FOCAL_POINT';

    public function __construct(private readonly ActorAccessChecker $accessChecker)
    {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::CREATE, self::VALIDATE, self::MANAGE_ADMINS, self::MANAGE_FOCAL_POINT], true)
            && $subject instanceof Actor;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Actor $actor */
        $actor = $subject;

        return match ($attribute) {
            self::EDIT => $this->accessChecker->isPlatformAdmin() || $this->accessChecker->canContribute($user, $actor),
            self::VALIDATE, self::MANAGE_ADMINS => $this->accessChecker->canValidate($user, $actor),
            self::MANAGE_FOCAL_POINT => $this->accessChecker->isPlatformAdmin(),
            default => false,
        };
    }
}