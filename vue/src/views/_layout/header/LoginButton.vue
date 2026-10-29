<template>
  <router-link
    v-if="!userStore.userIsLogged"
    append
    :to="{ query: { ...$route.query, dialog: DialogKey.AUTH_SIGN_IN } }"
    :class="{ Header__bannerLink: variant === 'link' }"
  >
    <v-btn v-if="variant === 'button'" color="main-red" prepend-icon="$accountCircle" flat>
      {{ $t('header.login') }}
    </v-btn>
    <template v-else>
      <v-icon icon="$accountCircle" />
      <span>{{ $t('header.login') }}</span>
    </template>
  </router-link>
  <v-menu location="bottom right" class="AuthMenu" v-else>
    <template v-slot:activator="{ props }">
      <v-btn
        v-if="variant === 'button'"
        base-color="white"
        class="text-main-blue"
        prepend-icon="$accountCircle"
        append-icon="$chevronDown"
        v-bind="props"
        flat
      >
        {{ $t('header.account') }}
      </v-btn>
      <div v-else class="Header__bannerLink" v-bind="props">
        <v-icon icon="$accountCircle" />
        <span>{{ $t('header.account') }}</span>
        <v-icon icon="$chevronDown" size="16" />
      </div>
    </template>

    <v-list class="mt-2">
      <v-list-item
        v-if="
          userStore.userHasRole(UserRoles.EDITOR_PROJECTS) || userStore.userIsAdmin()
        "
        @click="openProjectForm"
      >
        <template v-slot:prepend>
          <v-icon color="main-blue" icon="$plus"></v-icon>
        </template>
        <v-list-item-title>{{ $t('header.addProject') }}</v-list-item-title>
      </v-list-item>
      <v-list-item
        v-if="userStore.userHasRole(UserRoles.EDITOR_ACTORS) || userStore.userIsAdmin()"
        @click="actorsStore.setActorEditionMode(null)"
      >
        <template v-slot:prepend>
          <v-icon color="main-blue" icon="$plus"></v-icon>
        </template>
        <v-list-item-title>{{ $t('header.addActor') }}</v-list-item-title>
      </v-list-item>
      <v-list-item
        v-if="
          userStore.userHasRole(UserRoles.EDITOR_RESSOURCES) || userStore.userIsAdmin()
        "
        @click="openResourceForm"
      >
        <template v-slot:prepend>
          <v-icon color="main-blue" icon="$plus"></v-icon>
        </template>
        <v-list-item-title>{{ $t('header.addResource') }}</v-list-item-title>
      </v-list-item>
      <v-list-item v-if="userStore.userHasRole(UserRoles.EDITOR_DATA) || userStore.userIsAdmin()">
        <template v-slot:prepend>
          <v-icon color="main-blue" icon="$plus"></v-icon>
        </template>
        <v-list-item-title>{{ $t('header.addData') }}</v-list-item-title>
      </v-list-item>
      <v-divider class="my-2" v-if="userStore.userIsAdmin()" />
      <v-list-item :to="{ name: 'userAccount' }">
        <template v-slot:prepend>
          <v-icon color="main-blue" icon="$accountCircle"></v-icon>
        </template>
        <v-list-item-title color="main-blue" class="text-weight-bold">{{
          $t('header.account')
        }}</v-list-item-title>
      </v-list-item>
      <v-list-item v-if="userStore.userHasManagedActors" :to="{ name: 'myActors' }">
        <template v-slot:prepend>
          <v-icon color="main-blue" icon="$accountGroupOutline"></v-icon>
        </template>
        <v-list-item-title>{{ $t('actorTeam.myActors.menu') }}</v-list-item-title>
      </v-list-item>
      <v-list-item :to="{ name: 'myDiverCityBookings' }">
        <template v-slot:prepend>
          <v-icon color="main-blue" icon="$calendar"></v-icon>
        </template>
        <v-list-item-title>{{ $t('header.myBookings') }}</v-list-item-title>
      </v-list-item>
      <v-list-item
        v-if="userStore.userIsAdmin() || userStore.userIsDiverCitySpaceAdmin()"
        :to="{ name: 'admin' }"
      >
        <template v-slot:prepend>
          <v-icon color="main-blue" icon="$tune"></v-icon>
        </template>
        <v-list-item-title>{{ $t('header.administration') }}</v-list-item-title>
      </v-list-item>
      <v-list-item @click="userStore.signOut()">
        <template v-slot:prepend>
          <v-icon color="main-blue" icon="$logout"></v-icon>
        </template>
        <v-list-item-title>{{ $t('header.logout') }}</v-list-item-title>
      </v-list-item>
    </v-list>
  </v-menu>
</template>
<script setup lang="ts">
import { DialogKey } from '@/models/enums/app/DialogKey'
import { UserRoles } from '@/models/enums/auth/UserRoles'
import { useActorsStore } from '@/stores/actorsStore'
import { useActorTeamStore } from '@/stores/actorTeamStore'
import { useProjectStore } from '@/stores/projectStore'
import { useResourceStore } from '@/stores/resourceStore'
import { useUserStore } from '@/stores/userStore'

// 'button' (par défaut, comportement actuel) : bouton plein, utilisé dans le menu mobile.
// 'link' : rendu en lien discret icône+texte, pour s'intégrer au Header__banner desktop.
withDefaults(defineProps<{ variant?: 'button' | 'link' }>(), { variant: 'button' })

const userStore = useUserStore()
const actorsStore = useActorsStore()
const projectStore = useProjectStore()
const resourceStore = useResourceStore()
const teamStore = useActorTeamStore()

// Depuis le menu, aucun acteur n'est pré-sélectionné (contrairement au bouton de la page d'un acteur).
function openProjectForm() {
  teamStore.presetActorId = null
  projectStore.isProjectFormShown = true
}

function openResourceForm() {
  teamStore.presetActorId = null
  resourceStore.isResourceFormShown = true
}
</script>

<style lang="scss">
.AuthMenu {
  .v-menu > .v-overlay__content {
    border-radius: 6px;
  }

  .v-list-item-title {
    color: rgb(var(--v-theme-main-blue));
    font-weight: 600;
    font-size: 0.875rem;
  }

  .v-list-item__prepend {
    width: 2.25rem;
  }

  .v-icon {
    --v-icon-size-multiplier: 0.875;
  }
}
</style>