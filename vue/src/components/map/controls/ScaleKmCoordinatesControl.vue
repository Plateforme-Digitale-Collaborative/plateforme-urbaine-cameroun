<template>
  <div class="ScaleKmCoordinates">
    <!-- Cadre 1 : Échelle SIG (Texte à l'intérieur du trait en U) -->
    <div v-if="scaleLabel" class="ScaleKmCoordinates__card maplibregl-ctrl maplibregl-ctrl-group">
      <div class="ScaleKmCoordinates__scale">
        <div class="ScaleKmCoordinates__bar" :style="{ width: `${barWidth}px` }">
          <span class="ScaleKmCoordinates__label">{{ scaleLabel }}</span>
        </div>
      </div>
    </div>

    <!-- Cadre 2 : Coordonnées -->
    <div class="ScaleKmCoordinates__card maplibregl-ctrl maplibregl-ctrl-group">
      <div class="ScaleKmCoordinates__coords">
        <span>Lat : {{ latLabel }}</span>
        <span>Lng : {{ lngLabel }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useMyMapStore } from '@/stores/myMapStore'
import { computed, ref, watch } from 'vue'
import type { MapMouseEvent } from 'maplibre-gl'

// Largeur de référence (en px) utilisée pour mesurer la distance au sol
const REFERENCE_WIDTH_PX = 100

const mapStore = useMyMapStore()
const map = computed(() => mapStore.myMap?.map)

const scaleLabel = ref('')
const barWidth = ref(0)
const lat = ref<number | null>(null)
const lng = ref<number | null>(null)

const latLabel = computed(() => formatCoordinate(lat.value))
const lngLabel = computed(() => formatCoordinate(lng.value))

function formatCoordinate(value: number | null) {
  if (value === null) return '—'
  return `${value.toLocaleString('fr-FR', {
    minimumFractionDigits: 5,
    maximumFractionDigits: 5
  })}°`
}

// Arrondit une distance (en km) à une valeur ronde : 1, 2, 5, 10...
function getNiceDistanceKm(km: number) {
  const power = Math.pow(10, Math.floor(Math.log10(km)))
  const ratio = km / power
  const nice = ratio >= 5 ? 5 : ratio >= 2 ? 2 : 1
  return nice * power
}

function updateScale() {
  const currentMap = map.value
  if (!currentMap) return

  // Distance entre deux points au milieu de l'écran
  const y = currentMap.getCanvas().clientHeight / 2
  const left = currentMap.unproject([0, y])
  const right = currentMap.unproject([REFERENCE_WIDTH_PX, y])
  const meters = left.distanceTo(right)
  if (!meters) return

  const niceKm = getNiceDistanceKm(meters / 1000)
  barWidth.value = Math.round((niceKm * 1000 * REFERENCE_WIDTH_PX) / meters)

  // Affichage en mètres si < 1km, sinon en kilomètres
  if (niceKm < 1) {
    const niceMeters = Math.round(niceKm * 1000)
    scaleLabel.value = `${niceMeters.toLocaleString('fr-FR')} m`
  } else {
    scaleLabel.value = `${niceKm.toLocaleString('fr-FR', { maximumFractionDigits: 3 })} km`
  }
}

function onMouseMove(e: MapMouseEvent) {
  lat.value = e.lngLat.lat
  lng.value = e.lngLat.lng
}

watch(
  map,
  (currentMap, _oldMap, onCleanup) => {
    if (!currentMap) return
    currentMap.on('move', updateScale)
    currentMap.on('mousemove', onMouseMove)
    updateScale()

    onCleanup(() => {
      currentMap.off('move', updateScale)
      currentMap.off('mousemove', onMouseMove)
    })
  },
  { immediate: true }
)
</script>

<style scoped>
.ScaleKmCoordinates {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
  font-size: 0.75rem;
}

.ScaleKmCoordinates__card {
  padding: 0.35rem 0.65rem;
  display: flex;
  align-items: center;
  background-color: #ffffff;
  margin: 0 !important;
}

.ScaleKmCoordinates__scale {
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Trait en forme de U encadrant le texte */
.ScaleKmCoordinates__bar {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 1.2rem;
  border-bottom: 2px solid #333;
  border-left: 2px solid #333;
  border-right: 2px solid #333;
  box-sizing: border-box;
  padding: 0 0.25rem;
}

.ScaleKmCoordinates__label {
  font-size: 0.7rem;
  line-height: 1;
  color: #333;
}

.ScaleKmCoordinates__coords {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
</style>