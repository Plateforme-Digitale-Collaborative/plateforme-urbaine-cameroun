<?php

namespace App\Enum;

/**
 * Rôle d'un utilisateur vis-à-vis de la page d'un acteur.
 *
 * - FOCAL_POINT : point focal. Valide les modifications faites par les
 *   administrateurs de page, et nomme ces administrateurs.
 * - PAGE_ADMIN  : administrateur de page. Peut modifier l'acteur et ajouter /
 *   modifier ses projets et ressources, sous réserve de validation par le point focal.
 *
 * NB : le « référent » de l'acteur (contactName / contactPosition sur Actor) reste
 * une information de contact, distincte du point focal.
 */
enum ActorAssignmentRole: string
{
    case FOCAL_POINT = 'focal_point';
    case PAGE_ADMIN = 'page_admin';
}