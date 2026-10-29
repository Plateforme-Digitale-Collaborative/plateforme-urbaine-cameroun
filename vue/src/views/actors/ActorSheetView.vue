<template>
  <div class="ActorSheetView SheetView" v-if="actor">
    <div class="SheetView__block SheetView__block--left">
      <div class="SheetView__logoCtn show-sm">
        <img
          loading="lazy"
          :src="actor.logo.contentUrl"
          class="SheetView__logo"
          v-if="actor.logo"
        />
      </div>
      <SheetContentBanner
        :page="CommentOrigin.ACTOR"
        :id="actor.id"
        :slug="actor.slug"
        :title="actor.name"
        :subtitle="actor.acronym"
        :phone="actor.phone"
        :email="actor.email"
        :website="actor.website"
        :isEditable="isEditable"
        :updatedAt="actor.updatedAt"
        :map-route="actorMapRoute"
        :map-btn-tooltip="$t('actorPage.seeLocation')"
        :createdBy="actor.createdBy"
        @edit="editActor"
      />
      <ActorPendingBanner :actor="actor" class="mt-4" />
      <div class="ActorSheetView__manageBar" v-if="isManager">
        <v-btn
          variant="outlined"
          color="main-blue"
          prepend-icon="$accountGroupOutline"
          :to="{ name: 'actorManagement', params: { slug: actor.slug } }"
        >
          {{ $t('actorTeam.manage') }}
        </v-btn>
        <v-btn variant="outlined" color="main-blue" prepend-icon="$plus" @click="addProject">
          {{ $t('actorTeam.banner.addProject') }}
        </v-btn>
        <v-btn variant="outlined" color="main-blue" prepend-icon="$plus" @click="addResource">
          {{ $t('actorTeam.banner.addResource') }}
        </v-btn>
      </div>
      <div class="SheetView__contentCtn my-6" v-if="actor.description">
        <div class="SheetView__title SheetView__title--divider">
          {{ $t('actorPage.description') }}
        </div>
        <span v-html="formattedDescription"></span>
      </div>
      <ActorRelatedContent :actor="actor" v-if="!appStore.mobile" />
    </div>
    <div class="SheetView__block SheetView__block--right">
      <div class="SheetView__updatedAtCtn hide-sm">
        <UpdateInfoLabel :user="actor.createdBy" :date="actor.updatedAt" class="justify-end" />
        <PrintButton />
      </div>
      <div class="SheetView__logoCtn hide-sm">
        <img
          loading="lazy"
          :src="actor.logo?.contentsFilteredUrl?.thumbnail"
          alt=""
          v-if="actor.logo?.contentsFilteredUrl?.thumbnail"
          class="SheetView__logo"
        />
      </div>
      <ChipList :items="actor.thematics" />

      <div v-if="actor.odds && Array.isArray(actor.odds) && actor.odds.length > 0">
        <div class="SheetView__title SheetView__title--divider">
          <span>{{ $t('forms.odds.title') }}</span>
        </div>
        <div>
          <template v-for="odd in actor.odds?.sort((a, b) => parseInt(a) - parseInt(b))" :key="odd">
            <img
              class="SheetView__ODD mr-2"
              :src="`/img/odd/F-WEB-Goal-${odd}.webp`"
              :alt="odd"
              v-if="parseInt(odd) >= 1 && parseInt(odd) <= 17"
            />
            <span v-else>{{ $t('forms.odds.' + odd) }}</span>
          </template>
        </div>
      </div>

      <div>
        <div class="SheetView__title SheetView__title--divider mt-lg-12">
          <span>{{ $t('actorPage.adminScope') }}</span>
        </div>
        <span>{{ actor.administrativeScopes.map((x) => $t('actors.scope.' + x)).join(', ') }}</span>
        <AdminBoundariesButton :entity="actor" />
      </div>

      <div>
        <div
          class="SheetView__title SheetView__title--divider"
          v-if="actor.banoc || actor.banocUrl"
        >
          <span>{{ $t('forms.banoc.title') }}</span>
        </div>
        <div class="d-flex flex-column">
          <p v-if="actor.banoc">
            <span class="font-weight-bold">{{ $t('forms.banoc.code') + ' :' }}</span>
            {{ actor.banoc }}
          </p>
          <p v-if="actor.banocUrl">
            <span class="font-weight-bold">{{ $t('forms.banoc.url') + ' :' }}</span>
            <a :href="normalizeUrl(actor.banocUrl)" target="_blank">{{ actor.banocUrl }}</a>
          </p>
        </div>
      </div>

      <div class="SheetView__infoCard" v-if="actor.contactName || actor.contactPosition">
        <div>
          <h5 class="SheetView__title">{{ $t('actorPage.contact') }}</h5>
          <ContactCard :name="actor.contactName" :description="actor.contactPosition" />
        </div>
      </div>

      <div class="SheetView__infoCard" v-if="actor.officeName || actor.officeAddress">
        <div class="d-flex flex-row">
          <v-icon icon="$mapMarkerOutline" color="main-black" />
          <div class="ml-1">
            <p class="font-weight-bold">{{ actor.officeName }}</p>
            <p>{{ actor.officeAddress }}</p>
          </div>
        </div>
      </div>
    </div>
    <ActorRelatedContent :actor="actor" v-if="appStore.mobile" />
    <div class="SheetView__block SheetView__block--bottom">
      <SectionBanner :text="$t('actorPage.images')" v-if="actorImages.length" />
      <ImagesMosaic :images="actorImages" :key="mosaicKey" />
      <ContentDivider />
    </div>
  </div>
</template>
<script setup lang="ts">
import SectionBanner from '@/components/banners/SectionBanner.vue'
import ChipList from '@/components/content/ChipList.vue'
import ContactCard from '@/components/content/ContactCard.vue'
import ContentDivider from '@/components/content/ContentDivider.vue'
import ImagesMosaic from '@/components/content/ImagesMosaic.vue'
import AdminBoundariesButton from '@/components/content/adminBoundaries/AdminBoundariesButton.vue'
import PrintButton from '@/components/global/PrintButton.vue'
import type { Actor } from '@/models/interfaces/Actor'
import { CommentOrigin } from '@/models/interfaces/Comment'
import { formatHTMLForSheetView, normalizeUrl } from '@/services/utils/UtilsService'
import { useActorsStore } from '@/stores/actorsStore'
import { useActorTeamStore } from '@/stores/actorTeamStore'
import { useApplicationStore } from '@/stores/applicationStore'
import { useProjectStore } from '@/stores/projectStore'
import { useResourceStore } from '@/stores/resourceStore'
import { useUserStore } from '@/stores/userStore'
import SheetContentBanner from '@/views/_layout/sheet/SheetContentBanner.vue'
import UpdateInfoLabel from '@/views/_layout/sheet/UpdateInfoLabel.vue'
import ActorRelatedContent from '@/views/actors/components/ActorRelatedContent.vue'
import ActorPendingBanner from '@/views/actors/components/team/ActorPendingBanner.vue'
import { computed, onMounted, ref, watchEffect } from 'vue'
import { useRoute } from 'vue-router'

const appStore = useApplicationStore()
const userStore = useUserStore()
const actorsStore = useActorsStore()
const teamStore = useActorTeamStore()
const projectStore = useProjectStore()
const resourceStore = useResourceStore()
const actor = computed(() => actorsStore.selectedActor)
const actorMapRoute = computed(() => {
  return actor.value
    ? {
        name: 'map',
        query: { item: actor.value.id }
      }
    : null
})
const actorImages = computed(() => {
  const images = actor.value?.images ?? []
  const externalImages = actor.value?.externalImages ?? []
  return [...images, ...externalImages]
})

const mosaicKey = ref(0)

watchEffect(() => {
  if (actorImages.value) {
    mosaicKey.value++
  }
})

onMounted(async () => {
  appStore.isLoading = true
  const route = useRoute()
  await actorsStore.getActors()
  watchEffect(() => {
    if (actorsStore.dataLoaded) {
      if (actorsStore.selectedActor === null) {
        const actor: Actor | undefined = actorsStore.actors.find(
          (actor) => actor.slug === route.params.slug
        )
        actorsStore.setSelectedActor(actor?.id as string)
        appStore.isLoading = false
      } else {
        appStore.isLoading = false
      }
    }
  })
})

// Admin PDC, point focal ou administrateur de page de cet acteur : accès à la gestion de la page
const isManager = computed(
  () =>
    userStore.currentUser != null &&
    !!actor.value &&
    (userStore.userIsAdmin() || userStore.userManagesActor(actor.value))
)

// Modifier la page : admin PDC, point focal ou administrateur de page (dont les modifications passent par validation).
// Le créateur de la page en est devenu le point focal : il n'a plus de droit propre en tant que « créateur ».
const isEditable = computed(() => {
  return (
    userStore.currentUser != null &&
    (userStore.userIsAdmin() || (!!actor.value && userStore.userManagesActor(actor.value)))
  )
})

// Ajouter un projet / une ressource directement rattaché(e) à cet acteur
function addProject() {
  teamStore.presetActorId = actor.value?.id ?? null
  projectStore.isProjectFormShown = true
}

function addResource() {
  teamStore.presetActorId = actor.value?.id ?? null
  resourceStore.isResourceFormShown = true
}

function editActor() {
  actorsStore.setActorEditionMode(actor.value)
}

const formattedDescription = computed(() =>
  formatHTMLForSheetView(actor.value?.description as string)
)
</script>
<style lang="scss">
@import '@/assets/styles/views/SheetView';

.ActorSheetView__projectCardCtn {
  display: flex;
  flex-flow: row wrap;
  justify-content: center;
  gap: 2rem;
  > * {
    flex: 1 0 25rem;
  }
}

.ActorSheetView {
  &__manageBar {
    display: flex;
    flex-flow: row wrap;
    gap: 0.75rem;
    margin-top: 1rem;
  }
  &__logo {
    max-width: 100%;
  }
  &__contentCard {
    display: flex;
    padding: 1.5em;
    width: 100%;
    background-color: rgb(var(--v-theme-light-yellow));
  }
}

@media (max-width: $bp-xl) {
  .ActorSheetView {
    .SheetView__block--bottom {
      display: none;
    }
  }
}
</style>