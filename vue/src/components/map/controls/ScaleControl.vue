<template>
  <div v-if="scale" class="MapScale maplibregl-ctrl maplibregl-ctrl-group">{{ scale }}</div>
</template>

<script setup lang="ts">
import { useMyMapStore } from '@/stores/myMapStore'
import { computed, ref, watch } from 'vue'

const mapStore = useMyMapStore()
const map = computed(() => mapStore.myMap?.map)
const zoomLevel = ref(0)
const latitude = ref<number | null>(null)

function updateFromMap() {
  const currentMap = map.value
  if (!currentMap) return
  zoomLevel.value = currentMap.getZoom()
  latitude.value = currentMap.getCenter().lat
}

watch(
  map,
  (currentMap, _oldMap, onCleanup) => {
    if (!currentMap) return
    // Calcul initial : sans cela, l'échelle reste vide jusqu'au premier zoom
    updateFromMap()
    currentMap.on('zoom', updateFromMap)
    currentMap.on('moveend', updateFromMap)

    onCleanup(() => {
      currentMap.off('zoom', updateFromMap)
      currentMap.off('moveend', updateFromMap)
    })
  },
  { immediate: true }
)

const scale = computed(() => {
  if (!map.value || latitude.value === null) return ''

  // Formule de calcul de l'échelle à partir de la latitude, du zoom et du ratio de pixels
  // Cette formule est basée sur des tuiles de 256x256
  const dpi = 96 * devicePixelRatio
  const metersPerPixel =
    (156543.03 * Math.cos((latitude.value * Math.PI) / 180)) / Math.pow(2, zoomLevel.value)

  const pixelsPerCm = dpi / 2.54
  const groundDistanceMeters = metersPerPixel * pixelsPerCm
  let scaleDenominator = groundDistanceMeters * 100
  // Adaptation de la formule pour des tuiles de 512x512 si nécessaire
  if (mapStore.tileSize === 512) {
    scaleDenominator = scaleDenominator / 2
  }

  scaleDenominator = roundScale(scaleDenominator)
  // eslint-disable-next-line vue/no-side-effects-in-computed-properties
  mapStore.scaleDenominator = scaleDenominator

  return `1/${scaleDenominator.toLocaleString('fr-FR')}ème`
})

function roundScale(scale: number) {
  if (scale > 1000000) return Math.round(scale / 1000000) * 1000000
  if (scale > 1000) return Math.round(scale / 1000) * 1000
  return scale
}
</script>

<style scoped>
.MapScale {
  background-color: white !important;
  padding: 0.5rem 1rem;
  display: flex;
  align-items: center;
  margin: 0 !important;
  white-space: nowrap;
}
</style>