<template>
  <div class="MyActorsView" v-if="userStore.userIsLogged">
    <SectionBanner :text="$t('actorTeam.myActors.title')" />

    <p v-if="userStore.managedActors.length === 0" class="mt-6 text-medium-emphasis">
      {{ $t('actorTeam.myActors.empty') }}
    </p>

    <div class="MyActorsView__list" v-else>
      <v-card
        v-for="managed in userStore.managedActors"
        :key="managed.actorId"
        variant="outlined"
        class="MyActorsView__item"
      >
        <div>
          <div class="font-weight-bold">{{ managed.actorName }}</div>
          <div class="d-flex flex-wrap align-center ga-2 mt-1">
            <v-chip
              size="small"
              variant="flat"
              :color="managed.role === ActorAssignmentRole.FOCAL_POINT ? 'main-red' : 'main-blue'"
            >
              {{ $t('actorTeam.role.' + managed.role) }}
            </v-chip>
            <v-chip
              v-if="managed.role === ActorAssignmentRole.FOCAL_POINT && pendingFor(managed.actorId) > 0"
              size="small"
              variant="flat"
              color="main-yellow"
            >
              {{ $t('actorTeam.myActors.pending', { count: pendingFor(managed.actorId) }) }}
            </v-chip>
          </div>
        </div>
        <div class="d-flex flex-wrap ga-2" v-if="slugOf(managed.actorId)">
          <v-btn
            variant="outlined"
            color="main-blue"
            :to="{ name: 'actorProfile', params: { slug: slugOf(managed.actorId) } }"
          >
            {{ $t('actorTeam.myActors.view') }}
          </v-btn>
          <v-btn
            variant="flat"
            color="main-red"
            :to="{
              name: 'actorManagement',
              params: { slug: slugOf(managed.actorId) },
              query: { tab: pendingFor(managed.actorId) > 0 ? 'validations' : 'team' }
            }"
          >
            {{ $t('actorTeam.myActors.manage') }}
          </v-btn>
        </div>
      </v-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import SectionBanner from '@/components/banners/SectionBanner.vue'
import { ActorAssignmentRole } from '@/models/enums/actors/ActorTeam'
import { useActorsStore } from '@/stores/actorsStore'
import { useActorTeamStore } from '@/stores/actorTeamStore'
import { useApplicationStore } from '@/stores/applicationStore'
import { useUserStore } from '@/stores/userStore'
import { onMounted } from 'vue'

const userStore = useUserStore()
const actorsStore = useActorsStore()
const teamStore = useActorTeamStore()
const applicationStore = useApplicationStore()

const pendingFor = (actorId: string) => teamStore.pendingCountByActor[actorId] ?? 0
const slugOf = (actorId: string) => actorsStore.actors.find((a) => a.id === actorId)?.slug

onMounted(async () => {
  applicationStore.isLoading = true
  // Les rôles ont pu changer depuis la connexion (nomination par un point focal, par exemple).
  await userStore.refreshCurrentUser()
  await Promise.all([actorsStore.getActors(), teamStore.loadAllPending()])
  applicationStore.isLoading = false
})
</script>

<style lang="scss">
.MyActorsView {
  display: flex;
  flex-flow: column nowrap;
  width: 100%;
  margin-top: 2.5rem;

  &__list {
    display: flex;
    flex-flow: column nowrap;
    gap: 0.75rem;
    margin-top: 1.5rem;
  }

  &__item {
    display: flex;
    flex-flow: row wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
  }
}
</style>