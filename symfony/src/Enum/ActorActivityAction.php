<?php

namespace App\Enum;

enum ActorActivityAction: string
{
    case ACTOR_CREATED = 'actor_created';
    case FOCAL_POINT_ASSIGNED = 'focal_point_assigned';
    case FOCAL_POINT_ENDED = 'focal_point_ended';
    case PAGE_ADMIN_ADDED = 'page_admin_added';
    case PAGE_ADMIN_REMOVED = 'page_admin_removed';
    /** Modification / création appliquée directement (point focal ou admin PDC). */
    case DIRECT_CREATE = 'direct_create';
    case DIRECT_UPDATE = 'direct_update';
    /** Modification / création soumise par un administrateur de page. */
    case CHANGE_SUBMITTED = 'change_submitted';
    case CHANGE_APPROVED = 'change_approved';
    case CHANGE_REJECTED = 'change_rejected';
}