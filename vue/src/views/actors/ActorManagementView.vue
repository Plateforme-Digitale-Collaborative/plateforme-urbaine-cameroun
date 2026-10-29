<template>
  <div class="ActorManagementView" v-if="ready">
    <template v-if="actor && canAccess">
      <PageBanner :title="$t('actorTeam.title')" :subtitle="actor.name" />
      <div class="ActorManagementView__toolbar">
        <v-btn
          variant="text"
          color="main-blue"
          prepend-icon="$arrowLeft"
          :to="{ name: 'actorProfile', params: { slug: actor.slug } }"
        >
          {{ $t('actorTeam.backToActor') }}
        </v-btn>
        <v-chip v-if="role" :color="role === ActorAssignmentRole.FOCAL_POINT ? 'main-red' : 'main-blue'" variant="flat">
          {{ $t('actorTeam.role.' + role) }}
        </v-chip>
      </div>

      <v-tabs v-model="tab" color="main-blue" class="mb-6">
        <v-tab value="team">{{ $t('actorTeam.tabs.team') }}</v-tab>
        <v-tab value="validations">
          {{ canValidate ? $t('actorTeam.tabs.validations') : $t('actorTeam.tabs.myRequests') }}
          <v-badge
            v-if="canValidate && pendingCount > 0"
            :content="pendingCount"
            color="main-red"
            inline
          />
        </v-tab>
        <v-tab value="history" v-if="canValidate">{{ $t('actorTeam.tabs.history') }}</v-tab>
      </v-tabs>

      <v-window v-model="tab" :touch="false">
        <v-window-item value="team">
          <ActorTeamPanel v-if="tab === 'team'" :actor="actor" />
        </v-window-item>
        <v-window-item value="validations">
          <ActorChangeRequestsPanel v-if="tab === 'validations'" :actor="actor" />
        </v-window-item>
        <v-window-item value="history" v-if="canValidate">
          <ActorHistoryPanel v-if="tab === 'history'" :actor="actor" />
        </v-window-item>
      </v-window>
    </template>
    <v-alert v-else type="error" variant="tonal">{{ $t('actorTeam.accessDenied') }}</v-alert>
  </div>
</template>

<script setup lang="ts">
import PageBanner from '@/components/banners/PageBanner.vue'
import { ActorAssignmentRole } from '@/models/enums/actors/ActorTeam'
import { idFromIri } from '@/services/actors/ActorTeamService'
import { useActorsStore } from '@/stores/actorsStore'
import { useActorTeamStore } from '@/stores/actorTeamStore'
import { useApplicationStore } from '@/stores/applicationStore'
import { useUserStore } from '@/stores/userStore'
import ActorChangeRequestsPanel from '@/views/actors/components/team/ActorChangeRequestsPanel.vue'
import ActorHistoryPanel from '@/views/actors/components/team/ActorHistoryPanel.vue'
import ActorTeamPanel from '@/views/actors/components/team/ActorTeamPanel.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const actorsStore = useActorsStore()
const userStore = useUserStore()
const teamStore = useActorTeamStore()
const applicationStore = useApplicationStore()

const ready = ref(false)
const tab = ref<'team' | 'validations' | 'history'>('team')

const actor = computed(() => actorsStore.actors.find((a) => a.slug === route.params.slug) ?? null)
const role = computed(() => (actor.value ? userStore.userActorRole(actor.value) : null))
const canValidate = computed(() => !!actor.value && userStore.userCanValidateFor(actor.value))
const canAccess = computed(() => userStore.userIsAdmin() || role.value !== null)
const pendingCount = computed(
  () =>
    teamStore.allPendingRequests.filter(
      (request) => idFromIri(request.actor) === actor.value?.id
    ).length
)

onMounted(async () => {
  applicationStore.isLoading = true
  await actorsStore.getActors()
  if (canValidate.value) {
    await teamStore.loadAllPending()
  }
  const requestedTab = route.query.tab
  if (requestedTab === 'validations' || (requestedTab === 'history' && canValidate.value)) {
    tab.value = requestedTab
  }
  ready.value = true
  applicationStore.isLoading = false
})
</script>

<style lang="scss">
.ActorManagementView {
  display: flex;
  flex-flow: column nowrap;
  gap: 1rem;
  width: 100%;
  margin-top: 2.5rem;

  &__toolbar {
    display: flex;
    flex-flow: row wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }
}
</style>