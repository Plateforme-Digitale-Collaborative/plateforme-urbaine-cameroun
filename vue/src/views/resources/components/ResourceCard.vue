<template>
  <GenericInfoCard
    v-if="resource"
    :id="resource.id"
    :href="ResourceService.getLink(resource)"
    :title="resource.name"
    :description="resource.description"
    :type="ItemType.RESOURCE"
    :type-label="$t('resources.resourceType.' + resource.type)"
    :action-icon="icon"
    :is-editable="isEditable"
    :map-route="mapRoute"
    :image="resource.previewImage"
    class="ResourceCard"
    :edit-function="editResource"
    :slug="resource.slug"
  >
    <template #image v-if="isEvent && resource.startAt">
      <div class="ResourceCard__dateBanner">
        <span class="ResourceCard__date">{{ date }}</span>
        <span class="ResourceCard__month">{{ month }}</span>
      </div>
    </template>
    <template #description>
      <span class="InfoCard__title">{{ resource.name }}</span>
      <span
        class="InfoCard__subTitle"
        v-if="(isEvent && resource.startAt) || resource.geoData?.name"
      >
        <span v-if="isEvent && resource.startAt">
          <v-icon icon="$calendar" />
          <span>{{ dateRangeLabel }}</span>
        </span>
        <span class="InfoCard__location" v-if="resource.geoData && locationName">
          <v-icon icon="$mapMarkerOutline" />
          <span>{{ locationName }}</span>
        </span>
      </span>
      <span class="InfoCard__description">
        <div>{{ resource.description }}</div>
        <UpdateInfoLabel :date="resource.updatedAt" :user="resource.createdBy" />
      </span>
    </template>
    <template #comment>
      <CommentButton
        position="Card"
        :origin="CommentOrigin.RESOURCE"
        :originSlug="ResourceService.getLink(resource)"
      />
    </template>
    <template #footer-right>
      <v-icon class="InfoCard__actionIcon" :icon="icon" color="light-blue"></v-icon>
    </template>
  </GenericInfoCard>
</template>

<script setup lang="ts">
import CommentButton from '@/components/comments/CommentButton.vue'
import GenericInfoCard from '@/components/global/GenericInfoCard.vue'
import { ItemType } from '@/models/enums/app/ItemType'
import { ResourceFormat } from '@/models/enums/contents/ResourceFormat'
import { ResourceType } from '@/models/enums/contents/ResourceType'
import { CommentOrigin } from '@/models/interfaces/Comment'
import type { Resource } from '@/models/interfaces/Resource'
import GeocodingService from '@/services/map/GeocodingService'
import { ResourceService } from '@/services/resources/ResourceService'
import { getDateRangeLabel, localizeDate } from '@/services/utils/UtilsService'
import { useResourceStore } from '@/stores/resourceStore'
import { useUserStore } from '@/stores/userStore'
import UpdateInfoLabel from '@/views/_layout/sheet/UpdateInfoLabel.vue'
import { computed } from 'vue'

const resourceStore = useResourceStore()
const userStore = useUserStore()
const props = defineProps<{
  resource: Resource
}>()
const mapRoute = computed(() => {
  return props.resource?.geoData
    ? {
        name: 'map',
        query: { item: props.resource.id }
      }
    : null
})
const icon = computed(() => {
  switch (props.resource.format) {
    case ResourceFormat.VIDEO:
    case ResourceFormat.WEB:
      return '$openInNew'
    case ResourceFormat.XLSX:
    case ResourceFormat.PDF:
      return '$download'
    case ResourceFormat.IMAGE:
      return '$arrowRight'
    default:
      return undefined
  }
})

const isEditable = computed(() => {
  return (
    userStore.userIsAdmin() ||
    (props.resource?.createdBy?.id === userStore.currentUser?.id &&
      userStore.currentUser?.id != null) ||
    // point focal / administrateur de page de l'acteur auquel la ressource est rattachée
    (typeof props.resource?.actor === 'object' && userStore.userManagesActor(props.resource.actor))
  )
})
const locationName = computed(() =>
  props.resource?.geoData ? GeocodingService.getLocationName(props.resource.geoData) : null
)
const dateRangeLabel = computed(() =>
  getDateRangeLabel(props.resource.startAt, props.resource.endAt)
)
const isEvent = computed(() => props.resource.type === ResourceType.EVENTS)
const date = computed(() => new Date(props.resource.startAt).getDate())
const month = computed(() => localizeDate(props.resource.startAt, { month: 'short' }))

const editResource = () => {
  resourceStore.isResourceFormShown = true
  resourceStore.editedResourceId = props.resource.id
}
</script>

<style lang="scss">
.ResourceCard {
  .InfoCard__subTitle {
    font-weight: 500 !important;
  }
  .InfoCard__description {
    display: flex;
    flex-flow: column nowrap;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
  }
  .ResourceCard__dateBanner {
    background: rgb(var(--v-theme-main-yellow));
    color: rgb(var(--v-theme-main-blue));
    display: flex;
    flex-flow: column nowrap;
    align-items: center;
    padding: 1rem;
    position: absolute;
    min-width: 5.25rem;
    top: 0;
    right: 1rem;
    border-radius: 0 0 $dim-radius $dim-radius;

    .ResourceCard__date {
      font-weight: 700;
      font-size: 2.25rem;
      line-height: 2.25rem;
    }
    .ResourceCard__month {
      font-size: 1.25rem;
      text-transform: uppercase;
    }
  }
}
</style>