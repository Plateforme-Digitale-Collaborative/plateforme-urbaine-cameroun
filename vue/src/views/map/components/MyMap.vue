<template>
  <div class="MyMap">
    <Map class="MyMap__map" ref="my-map" />
    <BasemapPicker ref="basemap-picker" v-model="basemap" />
    <MapScaleControls
      ref="map-scale-controls"
      :show-scale="true"
      :show-km-coordinates="true"
      :show-attribution="false"
    />
    <MyMapLegend ref="map-legend" />
    <MyMapExportButton ref="map-export-button" />
    <QgisLayersQueryButton ref="qgis-query-button" />
    <MyMapCommentButton ref="map-comment-button" />
    <ToggleSidebarControl
      v-model="myMapStore.isLeftSidebarShown"
      :inversed-direction="true"
      :is-higlighted-when-off="true"
      ref="toggle-left-sidebar-control"
    />
    <ToggleSidebarControl
      style="order: -1"
      v-model="myMapStore.isRightSidebarShown"
      :is-higlighted-when-off="true"
      ref="toggle-right-sidebar-control"
    />
    <MyMapItemPopup />
  </div>
</template>

<script setup lang="ts">
import BasemapPicker from '@/components/map/controls/BasemapPicker.vue'
import MyMapLegend from '@/views/map/components/MyMapLegend.vue'
import ToggleSidebarControl from '@/components/map/controls/ToggleSidebarControl.vue'
import Map from '@/components/map/Map.vue'
import type Basemap from '@/models/interfaces/map/Basemap'
import MapService, { IControl } from '@/services/map/MapService'
import { useMyMapStore } from '@/stores/myMapStore'
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'
import MyMapItemPopup from '@/views/map/components/MyMapItemPopup.vue'
import MyMapExportButton from '@/views/map/components/export/MyMapExportButton.vue'
import MapScaleControls from '@/components/map/controls/MapScaleControls.vue'
import QgisLayersQueryButton from './QgisLayersQuery/QgisLayersQueryButton.vue'
import MyMapCommentButton from '@/views/map/components/MyMapCommentButton.vue'

type MapType = InstanceType<typeof Map>
const basemap = ref<Basemap>()
const myMapStore = useMyMapStore()
const myMap = useTemplateRef<MapType>('my-map')
const toggleRightSidebarControl = useTemplateRef('toggle-right-sidebar-control')
const toggleLeftSidebarControl = useTemplateRef('toggle-left-sidebar-control')
const basemapPicker = useTemplateRef('basemap-picker')
const mapLegend = useTemplateRef('map-legend')
const mapExportButton = useTemplateRef('map-export-button')
const mapScaleControls = useTemplateRef('map-scale-controls')
const qgisQueryButton = useTemplateRef('qgis-query-button')
const mapCommentControl = useTemplateRef('map-comment-button')
const map = computed(() => myMap.value?.map)

onMounted(() => {
  if (myMap.value) {
    myMapStore.myMap = myMap.value
  }
  if (map.value != null) {
    map.value.addControl(new IControl(basemapPicker), 'bottom-right')
    map.value.addControl(new IControl(toggleRightSidebarControl), 'top-right')
    map.value.addControl(new IControl(toggleLeftSidebarControl), 'top-left')
    map.value.addControl(new IControl(mapCommentControl), 'bottom-right')
    map.value.addControl(new IControl(mapLegend), 'bottom-right')
    map.value.addControl(new IControl(mapExportButton), 'bottom-right')
    map.value.addControl(new IControl(qgisQueryButton), 'bottom-right')
    // Échelle (« ème » et/ou km) + coordonnées du curseur
    map.value.addControl(new IControl(mapScaleControls), 'bottom-left')
    // If map has already been visited, we set the previous bbox
    if (myMapStore.bbox) {
      map.value.fitBounds(myMapStore.bbox)
    }
    map.value.on('moveend', () => {
      if (map.value?.getBounds()) {
        myMapStore.bbox = map.value?.getBounds()
      }
    })
    map.value.on('idle', () => {
      myMapStore.mapCanvasToDataUrl = map.value?.getCanvas().toDataURL() as string
    })
  }
})

watch(basemap, () => {
  if (map.value != null && basemap.value != null) {
    MapService.updateStyle(map.value, basemap.value).then(() => {
      // Check for the source tile size as the scale control is based on it
      const sources = map.value?.getStyle().sources
      for (const source in sources) {
        const tileSource = map.value?.getSource(source)
        if (tileSource && tileSource.tileSize) {
          myMapStore.tileSize = tileSource.tileSize
        }
      }
    })
  }
})

watch(
  () => myMapStore.mapSearch?.bbox,
  (newValue) => {
    if (map.value != null && newValue != null) {
      map.value.fitBounds(newValue, { padding: 75 })
    }
  }
)
</script>

<style lang="scss">
.MyMap {
  width: 100%;
  height: 100%;
  position: relative;

  .MyMap__map {
    width: 100%;
    height: 100%;

    .maplibregl-ctrl-top-right {
      align-items: flex-end;
    }
    .maplibregl-ctrl-bottom-right {
      align-items: flex-end;
    }

    // ---------------------------------------------------------------
    // Responsive des contrôles de la carte
    // Les colonnes haut-droite (5 boutons) et bas-droite (5 boutons +
    // attribution) ont besoin d'environ 530px de hauteur : en dessous,
    // elles se chevauchent. On réduit d'abord marges et espacements,
    // puis on réduit progressivement la taille des colonnes.
    // Les seuils supposent un header d'environ 5rem : à ajuster au besoin.
    // ---------------------------------------------------------------
    @media (max-width: 768px), (max-height: 600px) {
      --map-margin: 0.75rem;

      .maplibregl-ctrl-top-left,
      .maplibregl-ctrl-top-right,
      .maplibregl-ctrl-bottom-left,
      .maplibregl-ctrl-bottom-right,
      .maplibregl-ctrl-group {
        gap: 0.4rem;
      }
    }

    @media (max-height: 600px) {
      .maplibregl-ctrl-top-right,
      .maplibregl-ctrl-bottom-right,
      .maplibregl-ctrl-bottom-left {
        zoom: 0.9;
      }
    }
    @media (max-height: 520px) {
      .maplibregl-ctrl-top-right,
      .maplibregl-ctrl-bottom-right,
      .maplibregl-ctrl-bottom-left {
        zoom: 0.8;
      }
    }
    @media (max-height: 440px) {
      .maplibregl-ctrl-top-right,
      .maplibregl-ctrl-bottom-right,
      .maplibregl-ctrl-bottom-left {
        zoom: 0.7;
      }
    }
    @media (max-height: 380px) {
      .maplibregl-ctrl-top-right,
      .maplibregl-ctrl-bottom-right,
      .maplibregl-ctrl-bottom-left {
        zoom: 0.6;
      }
    }
  }
}
</style>