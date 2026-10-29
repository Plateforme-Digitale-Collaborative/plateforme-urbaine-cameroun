<template>
  <div class="ActorPendingBanner" v-if="role">
    <v-alert
      v-if="canValidate && pendingCount > 0"
      type="warning"
      variant="tonal"
      icon="$alertCircleOutline"
      class="mb-2"
    >
      <div class="d-flex flex-wrap align-center justify-space-between ga-2">
        <span>{{ $t('actorTeam.banner.pendingToReview', { count: pendingCount }) }}</span>
        <v-btn
          size="small"
          color="main-red"
          variant="flat"
          :to="{ name: 'actorManagement', params: { slug: actor.slug }, query: { tab: 'validations' } }"
        >
          {{ $t('actorTeam.banner.review') }}
        </v-btn>
      </div>
    </v-alert>

    <v-alert
      v-if="role === ActorAssignmentRole.PAGE_ADMIN"
      type="info"
      variant="tonal"
      icon="$informationOutline"
      density="compact"
    >
      <div class="d-flex flex-wrap align-center justify-space-between ga-2">
        <span>{{ $t('actorTeam.banner.pageAdminInfo') }}</span>
        <v-btn
          size="small"
          variant="text"
          color="main-blue"
          :to="{ name: 'actorManagement', params: { slug: actor.slug }, query: { tab: 'validations' } }"
        >
          {{ $t('actorTeam.banner.myRequests') }}
        </v-btn>
      </div>
    </v-alert>
  </div>
</template>

<script setup lang="ts">
import { ActorAssignmentRole } from '@/models/enums/actors/ActorTeam'
import type { Actor } from '@/models/interfaces/Actor'
import { idFromIri } from '@/services/actors/ActorTeamService'
import { useActorTeamStore } from '@/stores/actorTeamStore'
import { useUserStore } from '@/stores/userStore'
import { computed, onMounted } from 'vue'

const props = defineProps<{ actor: Actor }>()

const userStore = useUserStore()
const teamStore = useActorTeamStore()

const role = computed(() => userStore.userActorRole(props.actor))
const canValidate = computed(() => userStore.userCanValidateFor(props.actor))
const pendingCount = computed(
  () =>
    teamStore.allPendingRequests.filter((request) => idFromIri(request.actor) === props.actor.id)
      .length
)

onMounted(() => {
  if (canValidate.value) teamStore.loadAllPending()
})
</script>

<style lang="scss">
.ActorPendingBanner {
  width: 100%;
}
</style>