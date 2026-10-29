/** Rôle d'un utilisateur vis-à-vis de la page d'un acteur. */
export enum ActorAssignmentRole {
  FOCAL_POINT = 'focal_point',
  PAGE_ADMIN = 'page_admin'
}

export enum ActorChangeStatus {
  PENDING = 'pending',
  APPROVED = 'approved',
  REJECTED = 'rejected'
}

export enum ActorChangeOperation {
  CREATE = 'create',
  UPDATE = 'update'
}

export enum ActorChangeTargetType {
  ACTOR = 'actor',
  PROJECT = 'project',
  RESOURCE = 'resource'
}

export enum ActorActivityAction {
  ACTOR_CREATED = 'actor_created',
  FOCAL_POINT_ASSIGNED = 'focal_point_assigned',
  FOCAL_POINT_ENDED = 'focal_point_ended',
  PAGE_ADMIN_ADDED = 'page_admin_added',
  PAGE_ADMIN_REMOVED = 'page_admin_removed',
  DIRECT_CREATE = 'direct_create',
  DIRECT_UPDATE = 'direct_update',
  CHANGE_SUBMITTED = 'change_submitted',
  CHANGE_APPROVED = 'change_approved',
  CHANGE_REJECTED = 'change_rejected'
}