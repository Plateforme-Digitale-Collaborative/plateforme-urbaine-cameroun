<template>
  <div class="MyMapItemPopup" ref="active-item-card">
    <ProjectCard
      v-if="myMapStore.activeItemType === ItemType.PROJECT"
      class="ProjectCard ProjectCard--light"
      :project="myMapStore.activeItem as Project"
      :map="true"
    />
    <ResourceCard
      v-else-if="myMapStore.activeItemType === ItemType.RESOURCE"
      :resource="myMapStore.activeItem as Resource"
      class="ResourceCard ResourceCard--light"
    />
    <ActorCard
      v-else-if="myMapStore.activeItemType === ItemType.ACTOR"
      :actor="myMapStore.activeItem as Actor"
      class="ActorCard ActorCard--light"
    />
    <QgisCard
      v-if="myMapStore.activeItemType === 'QGIS'"
      :features="myMapStore.activeItem as FilteredQGISLayerFeatures[]"
    />
  </div>
</template>

<script setup lang="ts">
import { useMyMapStore } from '@/stores/myMapStore'
import ProjectCard from '@/views/projects/components/ProjectCard.vue'
import { computed, useTemplateRef, watch } from 'vue'
import ActorCard from '@/views/actors/components/ActorCard.vue'
import { ItemType } from '@/models/enums/app/ItemType'
import type { Project } from '@/models/interfaces/Project'
import type { Resource } from '@/models/interfaces/Resource'
import type { Actor } from '@/models/interfaces/Actor'
import type { LngLat } from 'maplibre-gl'
import QgisCard from './QgisLayersQuery/QgisCard.vue'
import type { FilteredQGISLayerFeatures } from '@/models/interfaces/map/AtlasMap'
import MapService from '@/services/map/MapService'
import { AppLayersService } from '@/services/map/AppLayersService'
import type { Item } from '@/models/interfaces/Item'

const myMapStore = useMyMapStore()
const activeItemCard = useTemplateRef('active-item-card')

const myMap = computed(() => myMapStore.myMap)
const map = computed(() => myMap.value?.map)

// Couches cliquables (RESOURCE n'a pas de couche sur la carte)
const POPUP_LAYERS = [ItemType.ACTOR, ItemType.PROJECT]

// Les handlers de clic sont enregistrés UNE seule fois par instance de carte
let handlersMap: unknown = null
const registerClickHandlers = () => {
  if (!myMap.value || !map.value || !activeItemCard.value) return
  if (handlersMap === map.value) return
  handlersMap = map.value
  POPUP_LAYERS.forEach((layer) => myMap.value?.addPopupOnClick(layer, activeItemCard.value, false))
}

// Se déclenche dès que la carte existe, y compris au remontage de la vue
watch(
  map,
  (m) => {
    if (m) MapService.isLoaded(m, registerClickHandlers)
  },
  { immediate: true }
)

watch(
  () => myMap.value?.activeFeatureId,
  (to, from) => {
    const initializing = from === undefined
    if (myMap.value == null || initializing || to == null) return
    myMapStore.activeItemId = myMap.value?.activeFeatureId
  }
)

// Réaffiche le popup une fois l'item chargé (sans réenregistrer de handlers)
watch(
  () => [myMapStore.activeItem, myMapStore.isMapAlreadyBeenMounted],
  async () => {
    await MapService.isLoaded(map.value, () => showPopup())
  }
)

const showPopup = () => {
  if (myMap.value == null) return
  if (myMapStore.activeItemType === 'QGIS') {
    myMap.value.addPopup(myMapStore.activeItemCoords as LngLat, activeItemCard.value, false)
    return
  }
  const coords = (myMapStore.activeItem as Item | null)?.geoData?.coords
  if (coords) myMap.value.addPopup(coords, activeItemCard.value, false)
}

// L'élément sélectionné est-il encore affiché sur la carte ?
const isActiveItemStillVisible = () => {
  const id = myMap.value?.activeFeatureId
  const layerName = myMap.value?.activeLayerName
  if (!id || !layerName) return true

  const isProject = layerName === ItemType.PROJECT
  const mainLayer = isProject ? myMapStore.projectLayer : myMapStore.actorLayer
  if (!mainLayer?.isShown) return false

  const items = isProject
    ? AppLayersService.filteredProjects.value
    : AppLayersService.filteredActors.value
  return items.some((i: any) => String(i.id) === String(id))
}

// Couche (ou thématique) décochée : on ferme le popup et on arrête le clignotement
watch(
  () => [
    myMapStore.projectLayer?.isShown,
    myMapStore.actorLayer?.isShown,
    myMapStore.projectSubLayers.map((l) => l.isShown),
    myMapStore.actorSubLayers.map((l) => l.isShown)
  ],
  () => {
    if (!isActiveItemStillVisible()) myMap.value?.closeSelection()
  },
  { deep: true }
)
</script>

<style lang="scss">
.maplibregl-popup:not(.show),
.maplibregl-popup:not(.show) * {
  pointer-events: none !important;
}
.MyMap {
  width: 100%;
  height: 100%;
  position: relative;

  .maplibregl-popup,
  .MyMapItemPopup .v-card {
    max-width: 22rem !important;
  }

  .MyMap__map {
    width: 100%;
    height: 100%;

    .maplibregl-ctrl-top-right {
      align-items: flex-end;
    }
  }
}
</style>