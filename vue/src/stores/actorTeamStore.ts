import { NotificationType } from '@/models/enums/app/NotificationType'
import { StoresList } from '@/models/enums/app/StoresList'
import {
  ActorAssignmentRole,
  ActorChangeStatus
} from '@/models/enums/actors/ActorTeam'
import type {
  ActorActivityLog,
  ActorAssignment,
  ActorChangeRequest
} from '@/models/interfaces/ActorTeam'
import { i18n } from '@/plugins/i18n'
import { ActorTeamService, idFromIri } from '@/services/actors/ActorTeamService'
import { addNotification } from '@/services/notifications/NotificationService'
import { useActorsStore } from '@/stores/actorsStore'
import { useUserStore } from '@/stores/userStore'
import { isAxiosError } from 'axios'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

/** Message lisible renvoyé par l'API (RFC 7807 / Hydra), à défaut le message de l'erreur. */
const errorMessage = (error: unknown): string => {
  if (isAxiosError(error)) {
    const data = error.response?.data as Record<string, any> | undefined
    return data?.detail ?? data?.['hydra:description'] ?? data?.description ?? error.message
  }
  return String(error)
}

export const useActorTeamStore = defineStore(StoresList.ACTOR_TEAM, () => {
  const loading = ref(false)
  /** Affectations en cours de l'acteur consulté. */
  const assignments = ref<ActorAssignment[]>([])
  /** Historique complet (en cours + terminées) des affectations de l'acteur consulté. */
  const assignmentHistory = ref<ActorAssignment[]>([])
  const changeRequests = ref<ActorChangeRequest[]>([])
  const activity = ref<ActorActivityLog[]>([])
  const activityTotal = ref(0)
  const activityPage = ref(1)
  /** Demandes en attente visibles de l'utilisateur, tous acteurs confondus. */
  const allPendingRequests = ref<ActorChangeRequest[]>([])
  /**
   * Acteur à pré-sélectionner dans les formulaires « ajouter un projet / une ressource »
   * lorsqu'on les ouvre depuis la page d'un acteur.
   */
  const presetActorId = ref<string | null>(null)

  const focalPoint = computed(
    () => assignments.value.find((a) => a.role === ActorAssignmentRole.FOCAL_POINT) ?? null
  )
  const pageAdmins = computed(() =>
    assignments.value.filter((a) => a.role === ActorAssignmentRole.PAGE_ADMIN)
  )

  const pendingCountByActor = computed(() => {
    const counts: Record<string, number> = {}
    allPendingRequests.value.forEach((request) => {
      const id = idFromIri(request.actor)
      counts[id] = (counts[id] ?? 0) + 1
    })
    return counts
  })

  /** Exécute une action en affichant l'erreur de l'API ; renvoie false en cas d'échec. */
  async function run(action: () => Promise<unknown>, errorKey: string): Promise<boolean> {
    loading.value = true
    try {
      await action()
      return true
    } catch (error) {
      addNotification(i18n.t(errorKey), NotificationType.ERROR, errorMessage(error))
      return false
    } finally {
      loading.value = false
    }
  }

  async function loadTeam(actorId: string) {
    await run(async () => {
      assignments.value = await ActorTeamService.getAssignments(actorId, true)
    }, 'actorTeam.errors.load')
  }

  async function loadAssignmentHistory(actorId: string) {
    await run(async () => {
      assignmentHistory.value = await ActorTeamService.getAssignments(actorId, false)
    }, 'actorTeam.errors.load')
  }

  async function addAssignment(actorId: string, targetUserId: number, role: ActorAssignmentRole) {
    const ok = await run(
      () => ActorTeamService.addAssignment({ actor: `/api/actors/${actorId}`, targetUserId, role }),
      'actorTeam.errors.assign'
    )
    if (ok) {
      addNotification(
        i18n.t(
          role === ActorAssignmentRole.FOCAL_POINT
            ? 'actorTeam.success.focalPointAssigned'
            : 'actorTeam.success.pageAdminAdded'
        ),
        NotificationType.SUCCESS
      )
      await loadTeam(actorId)
    }
    return ok
  }

  async function endAssignment(actorId: string, assignment: ActorAssignment) {
    const ok = await run(
      () => ActorTeamService.endAssignment(assignment.id),
      'actorTeam.errors.remove'
    )
    if (ok) {
      addNotification(i18n.t('actorTeam.success.assignmentEnded'), NotificationType.SUCCESS)
      await loadTeam(actorId)
    }
    return ok
  }

  async function loadChangeRequests(actorId: string, status?: ActorChangeStatus) {
    await run(async () => {
      changeRequests.value = await ActorTeamService.getChangeRequests(actorId, status)
    }, 'actorTeam.errors.load')
  }

  /** Charge toutes les demandes en attente visibles (pour les compteurs). Silencieux en cas d'échec. */
  async function loadAllPending() {
    try {
      allPendingRequests.value = await ActorTeamService.getChangeRequests(
        undefined,
        ActorChangeStatus.PENDING
      )
    } catch {
      allPendingRequests.value = []
    }
  }

  async function review(
    request: ActorChangeRequest,
    decision: ActorChangeStatus.APPROVED | ActorChangeStatus.REJECTED,
    comment?: string
  ) {
    const ok = await run(
      () => ActorTeamService.reviewChangeRequest(request.id, decision, comment),
      'actorTeam.errors.review'
    )
    if (ok) {
      addNotification(
        i18n.t(
          decision === ActorChangeStatus.APPROVED
            ? 'actorTeam.success.approved'
            : 'actorTeam.success.rejected'
        ),
        NotificationType.SUCCESS
      )
      if (decision === ActorChangeStatus.APPROVED) {
        // Les listes publiques doivent refléter la modification qui vient d'être publiée.
        await useActorsStore().getActors()
      }
      await loadAllPending()
    }
    return ok
  }

  async function loadActivity(actorId: string, page = 1) {
    await run(async () => {
      const result = await ActorTeamService.getActivity(actorId, page)
      activity.value = page === 1 ? result.items : [...activity.value, ...result.items]
      activityTotal.value = result.total
      activityPage.value = page
    }, 'actorTeam.errors.load')
  }

  /** À appeler quand le rôle de l'utilisateur sur un acteur a pu changer (création d'un acteur, nomination...). */
  async function refreshMyRoles() {
    await useUserStore().refreshCurrentUser()
  }

  return {
    loading,
    assignments,
    assignmentHistory,
    changeRequests,
    activity,
    activityTotal,
    activityPage,
    allPendingRequests,
    presetActorId,
    focalPoint,
    pageAdmins,
    pendingCountByActor,
    loadTeam,
    loadAssignmentHistory,
    addAssignment,
    endAssignment,
    loadChangeRequests,
    loadAllPending,
    review,
    loadActivity,
    refreshMyRoles
  }
})