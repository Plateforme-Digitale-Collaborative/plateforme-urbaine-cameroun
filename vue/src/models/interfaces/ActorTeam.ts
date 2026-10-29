import type {
  ActorActivityAction,
  ActorAssignmentRole,
  ActorChangeOperation,
  ActorChangeStatus,
  ActorChangeTargetType
} from '@/models/enums/actors/ActorTeam'

/** Affectation d'un utilisateur à la page d'un acteur, sur une période (endedAt null = en cours). */
export interface ActorAssignment {
  '@id': string
  id: string
  /** IRI de l'acteur, ex. /api/actors/{uuid} */
  actor: string
  role: ActorAssignmentRole
  userId: number | null
  userFullName: string | null
  userEmail: string | null
  startedAt: string
  endedAt: string | null
  endReason: string | null
}

export interface AssignableUser {
  id: number
  fullName: string
}

export interface ActorAssignmentSubmission {
  actor: string
  targetUserId: number
  role: ActorAssignmentRole
}

/** Modification soumise par un administrateur de page, en attente de validation du point focal. */
export interface ActorChangeRequest {
  '@id': string
  id: string
  actor: string
  targetType: ActorChangeTargetType
  targetId: string
  targetLabel: string | null
  operation: ActorChangeOperation
  /** Corps JSON de la modification proposée (UPDATE uniquement). */
  payload: Record<string, unknown> | null
  status: ActorChangeStatus
  requestedById: number | null
  requestedByName: string | null
  requestedAt: string
  reviewedByName: string | null
  reviewedAt: string | null
  reviewComment: string | null
}

export interface ActorActivityLog {
  '@id': string
  id: string
  actor: string
  userId: number | null
  userLabel: string | null
  action: ActorActivityAction
  targetType: ActorChangeTargetType | null
  targetId: string | null
  details: Record<string, unknown> | null
  createdAt: string
}

/** Entrée de `managedActors` renvoyée par /api/users/me. */
export interface ManagedActor {
  actorId: string
  actorName: string | null
  role: ActorAssignmentRole
}