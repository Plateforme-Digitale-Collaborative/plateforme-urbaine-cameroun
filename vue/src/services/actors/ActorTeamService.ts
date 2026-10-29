import { ActorChangeStatus, ActorChangeTargetType } from '@/models/enums/actors/ActorTeam'
import type {
  AssignableUser,
  ActorActivityLog,
  ActorAssignment,
  ActorAssignmentSubmission,
  ActorChangeRequest
} from '@/models/interfaces/ActorTeam'
import { apiClient } from '@/plugins/axios/api'

export const actorIri = (actorId: string) => `/api/actors/${actorId}`

/** Extrait l'UUID d'un IRI (/api/actors/{uuid}) ; renvoie la valeur telle quelle si ce n'est pas un IRI. */
export const idFromIri = (iri: string) => iri.split('/').pop() ?? iri

export class ActorTeamService {
  // --- Affectations (point focal / administrateurs de page) ---

  static async getAssignments(actorId: string, activeOnly: boolean): Promise<ActorAssignment[]> {
    const params: Record<string, unknown> = {
      actor: actorIri(actorId),
      'order[startedAt]': 'desc'
    }
    if (activeOnly) {
      params['exists[endedAt]'] = false
    }
    return (await apiClient.get('/api/actor_assignments', { params })).data['hydra:member']
  }

  /** Recherche par nom des utilisateurs affectables à la page de l'acteur. */
  static async searchAssignableUsers(actorId: string, query: string): Promise<AssignableUser[]> {
    return (await apiClient.get('/api/actor_assignable_users', { params: { actor: actorId, q: query } })).data
  }

  static async addAssignment(submission: ActorAssignmentSubmission): Promise<ActorAssignment> {
    return (await apiClient.post('/api/actor_assignments', submission)).data
  }

  /** Met fin à l'affectation (l'historique est conservé côté serveur). */
  static async endAssignment(assignmentId: string): Promise<void> {
    await apiClient.delete(`/api/actor_assignments/${assignmentId}`)
  }

  // --- Demandes de validation ---

  /** Sans `actorId` : toutes les demandes visibles de l'utilisateur (ses acteurs / ses propres demandes). */
  static async getChangeRequests(
    actorId?: string,
    status?: ActorChangeStatus
  ): Promise<ActorChangeRequest[]> {
    const params: Record<string, unknown> = { 'order[requestedAt]': 'desc' }
    if (actorId) params.actor = actorIri(actorId)
    if (status) params.status = status
    return (await apiClient.get('/api/actor_change_requests', { params })).data['hydra:member']
  }

  static async reviewChangeRequest(
    requestId: string,
    status: ActorChangeStatus.APPROVED | ActorChangeStatus.REJECTED,
    reviewComment?: string
  ): Promise<ActorChangeRequest> {
    return (
      await apiClient.patch(`/api/actor_change_requests/${requestId}/review`, {
        status,
        reviewComment: reviewComment || null
      })
    ).data
  }

  /** Aperçu de l'élément concerné (nom, description...) pour aider le point focal à décider. */
  static async getTargetPreview(
    type: ActorChangeTargetType,
    id: string
  ): Promise<Record<string, any> | null> {
    const path = {
      [ActorChangeTargetType.ACTOR]: 'actors',
      [ActorChangeTargetType.PROJECT]: 'projects',
      [ActorChangeTargetType.RESOURCE]: 'resources'
    }[type]
    try {
      return (await apiClient.get(`/api/${path}/${id}`)).data
    } catch {
      return null
    }
  }

  // --- Journal d'activité ---

  static async getActivity(
    actorId: string,
    page: number
  ): Promise<{ items: ActorActivityLog[]; total: number }> {
    const data = (
      await apiClient.get('/api/actor_activity_logs', {
        params: { actor: actorIri(actorId), page, 'order[createdAt]': 'desc' }
      })
    ).data
    return { items: data['hydra:member'], total: data['hydra:totalItems'] ?? 0 }
  }
}