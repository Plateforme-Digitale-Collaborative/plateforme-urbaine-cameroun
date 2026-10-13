<template>
  <div class="MyMapAtlasSummary" :type="type" @click="setActiveAtlas">
    <div class="MyMapAtlasSummary_content">
      <div class="MyMapAtlasSummary_logo" :type="type">
        <img loading="lazy" :src="atlas.logo.contentUrl" v-if="atlas.logo" />
      </div>
      <div class="MyMapAtlasSummary_desc">
        <TruncatedText class="MyMapAtlasSummary_title" :text="atlas.name" />
        <div class="MyMapAtlasSummary_details">
          {{ filteredLength }}
          {{
            type === AtlasGroup.PREDEFINED_MAP
              ? $t('myMap.atlases.map', { count: filteredLength })
              : $t('myMap.atlases.data', { count: filteredLength })
          }}
        </div>
      </div>
    </div>
    <v-btn
      size="small"
      icon="$arrowRight"
      class="MyMapAtlasSummary_btn text-dark-grey"
      @click.stop="setActiveAtlas"
    ></v-btn>
  </div>
</template>
<script setup lang="ts">
import { AtlasGroup } from '@/models/enums/geo/AtlasGroup'
import type { Atlas } from '@/models/interfaces/Atlas'
import TruncatedText from '@/components/common/TruncatedText.vue'
import { useMyMapStore } from '@/stores/myMapStore'
import { computed, inject, type Ref } from 'vue'

const props = defineProps<{
  atlas: Atlas
  type: AtlasGroup
}>()
const hideDetails: Ref<boolean> = inject('hideDetails') as Ref<boolean>
const mapStore = useMyMapStore()

function setActiveAtlas() {
  hideDetails.value = false
  if (props.type === AtlasGroup.PREDEFINED_MAP) {
    mapStore.activeAtlas.leftPanel.active = true
    mapStore.activeAtlas.leftPanel.atlasID = props.atlas['@id']
  } else {
    mapStore.activeAtlas.rightPanel.active = true
    mapStore.activeAtlas.rightPanel.atlasID = props.atlas['@id']
  }
}

const filteredLength = computed(() => {
  if (
    props.type === AtlasGroup.PREDEFINED_MAP ||
    !mapStore.atlasSearchText ||
    props.atlas.name.toLowerCase().includes(mapStore.atlasSearchText.toLowerCase())
  ) {
    return props.atlas.maps.length
  }
  return props.atlas.maps.filter(
    (map) =>
      map.name.toLowerCase().includes(mapStore.atlasSearchText.toLowerCase()) ||
      map.qgisProject.layers?.some((layer) =>
        layer.toLowerCase().includes(mapStore.atlasSearchText.toLowerCase())
      )
  ).length
})
</script>

<style>
.MyMapAtlasSummary {
  display: flex;
  width: 100%;
  min-width: 0;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  border-radius: 5px;
  padding: 0.5rem 1rem;
  cursor: pointer;
  transition: background-color 0.15s ease-in;
}
.MyMapAtlasSummary:hover {
  background-color: rgba(var(--v-theme-main-blue), 0.05);
}
.MyMapAtlasSummary[style*='display: none'] {
  display: none !important;
}
.MyMapAtlasSummary[type='Catalogue'] {
  border: 1px solid rgb(var(--v-theme-dark-grey));
}

.MyMapAtlasSummary[type='Observatoire'] {
  border: 1px solid rgb(var(--v-theme-main-blue));
  box-shadow: 0px 2px 0px 0px rgb(var(--v-theme-main-blue));
  padding: 0.5rem 1rem;
}

.MyMapAtlasSummary_content {
  display: flex;
  flex-flow: row nowrap;
  flex: 1 1 auto;
  min-width: 0;
}

.MyMapAtlasSummary_btn {
  flex: 0 0 auto;
}

.MyMapAtlasSummary_logo {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  img {
    width: 2rem;
    height: 2rem;
    object-fit: cover;
    margin-right: 10px;
    border-radius: 50%;
  }
  &[type='Observatoire'] {
    img {
      width: 3rem;
      height: 3rem;
    }
  }
}

.MyMapAtlasSummary_desc {
  display: flex;
  flex-flow: column nowrap;
  align-items: stretch;
  justify-content: center;
  flex: 1 1 auto;
  min-width: 0;
}

.MyMapAtlasSummary_title {
  font-weight: 600;
}
</style>