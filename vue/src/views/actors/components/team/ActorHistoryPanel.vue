<template>
  <div class="ActorHistoryPanel">
    <section class="mb-8">
      <h4 class="ActorHistoryPanel__title">{{ $t('actorTeam.history.assignments') }}</h4>
      <p v-if="teamStore.assignmentHistory.length === 0" class="text-medium-emphasis">
        {{ $t('actorTeam.history.noAssignments') }}
      </p>
      <v-table v-else density="comfortable">
        <thead>
          <tr>
            <th>{{ $t('actorTeam.history.person') }}</th>
            <th>{{ $t('actorTeam.history.role') }}</th>
            <th>{{ $t('actorTeam.history.period') }}</th>
            <th>{{ $t('actorTeam.history.endReason') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="assignment in teamStore.assignmentHistory" :key="assignment.id">
            <td>{{ assignment.userFullName ?? $t('actorTeam.history.unknownUser') }}</td>
            <td>
              <v-chip
                size="small"
                variant="tonal"
                :color="assignment.role === ActorAssignmentRole.FOCAL_POINT ? 'main-red' : 'main-blue'"
              >
                {{ $t('actorTeam.role.' + assignment.role) }}
              </v-chip>
            </td>
            <td>
              <template v-if="assignment.endedAt">
                {{
                  $t('actorTeam.history.fromTo', {
                    start: localizeDate(assignment.startedAt),
                    end: localizeDate(assignment.endedAt)
                  })
                }}
              </template>
              <template v-else>
                {{ $t('actorTeam.history.from', { start: localizeDate(assignment.startedAt) }) }}
                <v-chip size="x-small" color="main-green" variant="flat" class="ml-1">
                  {{ $t('actorTeam.history.ongoing') }}
                </v-chip>
              </template>
            </td>
            <td>{{ assignment.endReason ?? '' }}</td>
          </tr>
        </tbody>
      </v-table>
    </section>

    <section>
      <h4 class="ActorHistoryPanel__title">{{ $t('actorTeam.history.activity') }}</h4>
      <p v-if="teamStore.activity.length === 0" class="text-medium-emphasis">
        {{ $t('actorTeam.history.noActivity') }}
      </p>
      <v-table v-else density="comfortable">
        <thead>
          <tr>
            <th>{{ $t('actorTeam.history.when') }}</th>
            <th>{{ $t('actorTeam.history.who') }}</th>
            <th>{{ $t('actorTeam.history.what') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in teamStore.activity" :key="log.id">
            <td class="text-no-wrap">
              {{ localizeDate(log.createdAt) }} {{ localizeTime(log.createdAt) }}
            </td>
            <td>{{ log.userLabel ?? $t('actorTeam.history.unknownUser') }}</td>
            <td>{{ describe(log) }}</td>
          </tr>
        </tbody>
      </v-table>
      <div class="text-center mt-4" v-if="teamStore.activity.length < teamStore.activityTotal">
        <v-btn variant="outlined" color="main-blue" :loading="teamStore.loading" @click="loadMore">
          {{ $t('actorTeam.history.loadMore') }}
        </v-btn>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ActorAssignmentRole } from '@/models/enums/actors/ActorTeam'
import type { Actor } from '@/models/interfaces/Actor'
import type { ActorActivityLog } from '@/models/interfaces/ActorTeam'
import { i18n } from '@/plugins/i18n'
import { localizeDate, localizeTime } from '@/services/utils/UtilsService'
import { useActorTeamStore } from '@/stores/actorTeamStore'
import { onMounted } from 'vue'

const props = defineProps<{ actor: Actor }>()
const teamStore = useActorTeamStore()

onMounted(() => {
  teamStore.loadAssignmentHistory(props.actor.id)
  teamStore.loadActivity(props.actor.id, 1)
})

const loadMore = () => teamStore.loadActivity(props.actor.id, teamStore.activityPage + 1)

/** Phrase décrivant l'action, construite à partir des détails enregistrés. */
function describe(log: ActorActivityLog): string {
  const details = log.details ?? {}
  return i18n.t(`actorTeam.activity.${log.action}`, {
    name: String(details.name ?? ''),
    focalPointName: String(details.focalPointName ?? ''),
    adminName: String(details.adminName ?? ''),
    userName: String(details.userName ?? '')
  })
}
</script>

<style lang="scss">
.ActorHistoryPanel {
  &__title {
    font-size: 1.125rem;
    font-weight: 700;
    color: rgb(var(--v-theme-main-blue));
    margin-bottom: 0.75rem;
  }
}
</style>