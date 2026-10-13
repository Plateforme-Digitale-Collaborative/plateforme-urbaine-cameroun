<template>
  <div class="MapScaleControls maplibregl-ctrl">
    <ScaleControl v-if="showScale" />
    <ScaleKmCoordinatesControl v-if="showKmCoordinates" />
  </div>
</template>

<script setup lang="ts">
import ScaleControl from '@/components/map/controls/ScaleControl.vue'
import ScaleKmCoordinatesControl from '@/components/map/controls/ScaleKmCoordinatesControl.vue'
import { useMyMapStore } from '@/stores/myMapStore'
import { computed, onBeforeUnmount, watch } from 'vue'

const HIDE_ATTRIBUTION_CLASS = 'MyMap--hide-attribution'

const props = withDefaults(
  defineProps<{
    /** Échelle en « ème » (ex. 1/25 000ème) */
    showScale?: boolean
    /** Échelle en km + latitude / longitude du curseur */
    showKmCoordinates?: boolean
    /** Texte « © MapTiler © OpenStreetMap contributors » */
    showAttribution?: boolean
  }>(),
  {
    showScale: true,
    showKmCoordinates: true,
    showAttribution: false
  }
)

const mapStore = useMyMapStore()
const map = computed(() => mapStore.myMap?.map)

// Affiche / masque l'attribution via une classe sur le conteneur de la carte
watch(
  [map, () => props.showAttribution],
  ([currentMap, show]) => {
    currentMap?.getContainer().classList.toggle(HIDE_ATTRIBUTION_CLASS, !show)
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  map.value?.getContainer().classList.remove(HIDE_ATTRIBUTION_CLASS)
})
</script>

<style scoped>
/* Toujours affiché en ligne, sans retour à la ligne, sur tous les écrans */
.MapScaleControls {
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: center;
  gap: 0.5rem;
  width: max-content;
  background: transparent;
  box-shadow: none;
}

/* Neutralise les marges/float que MapLibre applique aux contrôles du coin bas-gauche */
.MapScaleControls :deep(.maplibregl-ctrl) {
  margin: 0 !important;
  float: none !important;
}

/* Les éléments gardent leur taille naturelle : pas de retour à la ligne interne */
.MapScaleControls > :deep(*) {
  flex: 0 0 auto;
}

/* Responsive : on réduit progressivement la taille au lieu d'empiler */
@media (max-width: 768px) {
  .MapScaleControls {
    gap: 0.3rem;
    zoom: 0.9;
  }
}

@media (max-width: 600px) {
  .MapScaleControls {
    zoom: 0.8;
  }
}

@media (max-width: 480px) {
  .MapScaleControls {
    gap: 0.25rem;
    zoom: 0.7;
  }
}

@media (max-width: 380px) {
  .MapScaleControls {
    zoom: 0.6;
  }
}
</style>

<!-- Style global : cible l'attribution MapLibre, qui est hors du composant -->
<style>
.MyMap--hide-attribution .maplibregl-ctrl-attrib {
  display: none !important;
}
</style>