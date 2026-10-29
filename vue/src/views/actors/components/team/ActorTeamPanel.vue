<template>
  <div class="ActorTeamPanel">
    <v-alert
      v-if="!canValidate"
      type="info"
      variant="tonal"
      density="compact"
      class="mb-6"
      icon="$informationOutline"
    >
      {{ $t('actorTeam.team.pageAdminOnlyInfo') }}
    </v-alert>

    <!-- Point focal -->
    <section class="ActorTeamPanel__section">
      <h4 class="ActorTeamPanel__title">{{ $t('actorTeam.team.focalPoint') }}</h4>
      <p class="ActorTeamPanel__help">{{ $t('actorTeam.roleHelp.focal_point') }}</p>

      <v-card variant="outlined" class="ActorTeamPanel__card" v-if="teamStore.focalPoint">
        <div class="ActorTeamPanel__person">
          <v-icon icon="$accountStarOutline" color="main-blue" size="large" />
          <div>
            <div class="font-weight-bold">{{ teamStore.focalPoint.userFullName }}</div>
            <div class="text-caption" v-if="teamStore.focalPoint.userEmail">
              {{ teamStore.focalPoint.userEmail }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{
                $t('actorTeam.team.since', { date: localizeDate(teamStore.focalPoint.startedAt) })
              }}
            </div>
          </div>
        </div>
        <div class="ActorTeamPanel__cardActions" v-if="userStore.userIsAdmin()">
          <v-btn
            variant="outlined"
            color="main-blue"
            prepend-icon="$swapHorizontal"
            @click="openFocalDialog"
          >
            {{ $t('actorTeam.team.replaceFocalPoint') }}
          </v-btn>
          <v-btn
            variant="text"
            color="main-red"
            prepend-icon="$closeCircleOutline"
            @click="askRemove(teamStore.focalPoint)"
          >
            {{ $t('actorTeam.team.remove') }}
          </v-btn>
        </div>
      </v-card>
      <div v-else class="ActorTeamPanel__empty">
        <span>{{ $t('actorTeam.team.noFocalPoint') }}</span>
        <v-btn
          v-if="userStore.userIsAdmin()"
          class="ml-4"
          color="main-red"
          variant="flat"
          prepend-icon="$accountPlusOutline"
          @click="openFocalDialog"
        >
          {{ $t('actorTeam.team.assignFocalPoint') }}
        </v-btn>
      </div>
      <p class="text-caption text-medium-emphasis mt-2" v-if="!userStore.userIsAdmin() && canValidate">
        {{ $t('actorTeam.team.focalPointAdminOnly') }}
      </p>
    </section>

    <!-- Administrateurs de page -->
    <section class="ActorTeamPanel__section" v-if="canValidate">
      <h4 class="ActorTeamPanel__title">{{ $t('actorTeam.team.pageAdmins') }}</h4>
      <p class="ActorTeamPanel__help">{{ $t('actorTeam.roleHelp.page_admin') }}</p>

      <div v-if="teamStore.pageAdmins.length === 0" class="ActorTeamPanel__empty">
        {{ $t('actorTeam.team.noPageAdmins') }}
      </div>
      <v-card
        v-for="admin in teamStore.pageAdmins"
        :key="admin.id"
        variant="outlined"
        class="ActorTeamPanel__card"
      >
        <div class="ActorTeamPanel__person">
          <v-icon icon="$shieldAccountOutline" color="main-blue" size="large" />
          <div>
            <div class="font-weight-bold">{{ admin.userFullName }}</div>
            <div class="text-caption" v-if="admin.userEmail">{{ admin.userEmail }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ $t('actorTeam.team.since', { date: localizeDate(admin.startedAt) }) }}
            </div>
          </div>
        </div>
        <v-btn
          variant="text"
          color="main-red"
          prepend-icon="$closeCircleOutline"
          @click="askRemove(admin)"
        >
          {{ $t('actorTeam.team.remove') }}
        </v-btn>
      </v-card>

      <v-form class="ActorTeamPanel__addForm" @submit.prevent="addPageAdmin">
        <label class="Form__label">{{ $t('actorTeam.team.addPageAdmin') }}</label>
        <div class="d-flex align-start ga-3">
          <AssignableUserPicker
            class="flex-grow-1"
            v-model="adminUser"
            :actor-id="actor.id"
            :error-messages="adminError"
          />
          <v-btn
            type="submit"
            color="main-red"
            variant="flat"
            prepend-icon="$accountPlusOutline"
            :loading="teamStore.loading"
            :disabled="!adminUser"
          >
            {{ $t('actorTeam.team.add') }}
          </v-btn>
        </div>
      </v-form>
    </section>

    <!-- Désignation / remplacement du point focal (admin PDC) -->
    <v-dialog v-model="focalDialog" max-width="32rem">
      <v-card class="pa-4">
        <v-card-title>
          {{
            teamStore.focalPoint
              ? $t('actorTeam.team.replaceFocalPoint')
              : $t('actorTeam.team.assignFocalPoint')
          }}
        </v-card-title>
        <v-card-text>
          <v-alert
            v-if="teamStore.focalPoint"
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ $t('actorTeam.team.currentFocalPoint', { name: teamStore.focalPoint.userFullName }) }}
            — {{ $t('actorTeam.team.replaceWarning') }}
          </v-alert>
          <AssignableUserPicker
            v-model="focalUser"
            :actor-id="actor.id"
            :error-messages="focalError"
            autofocus
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="focalDialog = false">{{ $t('actorTeam.team.cancel') }}</v-btn>
          <v-btn
            color="main-red"
            variant="flat"
            :loading="teamStore.loading"
            :disabled="!focalUser"
            @click="assignFocalPoint"
          >
            {{ $t('actorTeam.team.assign') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirmation de retrait -->
    <v-dialog v-model="removeDialog" max-width="30rem">
      <v-card class="pa-4" v-if="toRemove">
        <v-card-title>
          {{
            toRemove.role === ActorAssignmentRole.FOCAL_POINT
              ? $t('actorTeam.team.confirmRemoveFocalTitle')
              : $t('actorTeam.team.confirmRemoveTitle')
          }}
        </v-card-title>
        <v-card-text>
          {{
            toRemove.role === ActorAssignmentRole.FOCAL_POINT
              ? $t('actorTeam.team.confirmRemoveFocalText')
              : $t('actorTeam.team.confirmRemoveText', { name: toRemove.userFullName })
          }}
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="removeDialog = false">{{ $t('actorTeam.team.cancel') }}</v-btn>
          <v-btn color="main-red" variant="flat" :loading="teamStore.loading" @click="confirmRemove">
            {{ $t('actorTeam.team.remove') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ActorAssignmentRole } from '@/models/enums/actors/ActorTeam'
import type { Actor } from '@/models/interfaces/Actor'
import type { ActorAssignment, AssignableUser } from '@/models/interfaces/ActorTeam'
import AssignableUserPicker from '@/views/actors/components/team/AssignableUserPicker.vue'
import { i18n } from '@/plugins/i18n'
import { localizeDate } from '@/services/utils/UtilsService'
import { useActorTeamStore } from '@/stores/actorTeamStore'
import { useUserStore } from '@/stores/userStore'
import { computed, onMounted, ref } from 'vue'

const props = defineProps<{ actor: Actor }>()

const teamStore = useActorTeamStore()
const userStore = useUserStore()

const canValidate = computed(() => userStore.userCanValidateFor(props.actor))

const adminUser = ref<AssignableUser | null>(null)
const adminError = ref('')
const focalDialog = ref(false)
const focalUser = ref<AssignableUser | null>(null)
const focalError = ref('')
const removeDialog = ref(false)
const toRemove = ref<ActorAssignment | null>(null)

onMounted(() => teamStore.loadTeam(props.actor.id))

async function addPageAdmin() {
  adminError.value = ''
  if (!adminUser.value) {
    adminError.value = i18n.t('actorTeam.team.selectUser')
    return
  }
  const ok = await teamStore.addAssignment(
    props.actor.id,
    adminUser.value.id,
    ActorAssignmentRole.PAGE_ADMIN
  )
  if (ok) adminUser.value = null
}

function openFocalDialog() {
  focalUser.value = null
  focalError.value = ''
  focalDialog.value = true
}

async function assignFocalPoint() {
  focalError.value = ''
  if (!focalUser.value) {
    focalError.value = i18n.t('actorTeam.team.selectUser')
    return
  }
  const ok = await teamStore.addAssignment(
    props.actor.id,
    focalUser.value.id,
    ActorAssignmentRole.FOCAL_POINT
  )
  if (ok) focalDialog.value = false
}

function askRemove(assignment: ActorAssignment) {
  toRemove.value = assignment
  removeDialog.value = true
}

async function confirmRemove() {
  if (!toRemove.value) return
  const ok = await teamStore.endAssignment(props.actor.id, toRemove.value)
  if (ok) removeDialog.value = false
}
</script>

<style lang="scss">
.ActorTeamPanel {
  display: flex;
  flex-direction: column;
  gap: 2rem;

  &__title {
    font-size: 1.125rem;
    font-weight: 700;
    color: rgb(var(--v-theme-main-blue));
  }

  &__help {
    margin: 0.25rem 0 1rem;
    color: rgb(var(--v-theme-dark-grey));
    font-size: 0.875rem;
  }

  &__card {
    display: flex;
    flex-flow: row wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
  }

  &__person {
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  &__cardActions {
    display: flex;
    flex-flow: row wrap;
    gap: 0.5rem;
  }

  &__empty {
    display: flex;
    align-items: center;
    flex-flow: row wrap;
    padding: 1rem;
    background-color: rgb(var(--v-theme-light-grey));
    border-radius: 4px;
    margin-bottom: 0.75rem;
  }

  &__addForm {
    margin-top: 1.5rem;
    max-width: 36rem;
  }
}
</style>