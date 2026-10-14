import actorLayerIcon from '@/assets/images/icons/map/actor_icon.png'
import projectLayerIcon from '@/assets/images/icons/map/project_icon.png'
import { ItemType } from '@/models/enums/app/ItemType'
import { Thematic } from '@/models/enums/contents/Thematic'
import type { ThematicItem } from '@/models/interfaces/common/ThematicItem'
import type { Layer } from '@/models/interfaces/map/Layer'
import { i18n } from '@/plugins/i18n'
import { useActorsStore } from '@/stores/actorsStore'
import { useApplicationStore } from '@/stores/applicationStore'
import { useMyMapStore } from '@/stores/myMapStore'
import { useProjectStore } from '@/stores/projectStore'
import { computed } from 'vue'
import LayerService from './LayerService'
import MapService from './MapService'
import type PersistentGeoJSON from '@/models/interfaces/map/PersistentGeoJSON'

export class AppLayersService {
  private static mapThematics = Object.values(Thematic).map((t, i) => {
    return {
      id: i,
      name: t,
      isShown: true
    }
  })
  static filteredProjects = computed(() => {
    const projectStore = useProjectStore()
    const myMapStore = useMyMapStore()
    return this.filterByThematic(projectStore.projects, myMapStore.projectSubLayers as Layer[])
  })
  
  static filteredActors = computed(() => {
    const actorStore = useActorsStore()
    const myMapStore = useMyMapStore()
    return this.filterByThematic(
      actorStore.actorsList as ThematicItem[],
      myMapStore?.actorSubLayers as Layer[]
    )
  })

  static async initApplicationLayers(): Promise<void> {
    const projectStore = useProjectStore()
    const actorStore = useActorsStore()
    const applicationStore = useApplicationStore()
    const myMapStore = useMyMapStore()
    try {
      await Promise.all([actorStore.getAll(), projectStore.getAll()])
      if (!myMapStore.isMapAlreadyBeenMounted) {
        this.initMainLayers()
        this.initSubLayers()
      }

      if (myMapStore.map == null) return
      MapService.isLoaded(myMapStore.map, async () => {
        await this.setPlatformDataLayers()
        applicationStore.isLoading = false
      })
    } catch (error) {
      console.error('Erreur lors de l’initialisation des couches :', error)
      return Promise.reject(error)
    }
  }

  static initMainLayers() {
    const myMapStore = useMyMapStore()
    let showProject = false,
      showActor = false

    if (myMapStore?.deserializedMapState) {
      showProject = !!myMapStore.deserializedMapState?.layers?.projects
      showActor = !!myMapStore.deserializedMapState?.layers?.actors
    }

    myMapStore!.projectLayer = LayerService.initLayer(
      {
        id: ItemType.PROJECT,
        name: i18n.t('myMap.rightSidebar.layers.itemType.' + ItemType.PROJECT),
        icon: projectLayerIcon
      },
      showProject
    )
    myMapStore!.actorLayer = LayerService.initLayer(
      {
        id: ItemType.ACTOR,
        name: i18n.t('myMap.rightSidebar.layers.itemType.' + ItemType.ACTOR),
        icon: actorLayerIcon
      },
      showActor
    )
  }

  static initSubLayers() {
    const myMapStore = useMyMapStore()
    const mapThematics = Object.values(Thematic).map((t, i) => {
      return {
        id: i,
        name: t,
        isShown: true
      }
    })

    let projectThematics = this.mapThematics
    let actorsThematics = this.mapThematics

    if (myMapStore?.deserializedMapState) {
      projectThematics = mapThematics.map((x) => {
        return {
          ...x,
          isShown: myMapStore?.deserializedMapState?.layers?.projects?.includes(x.id) as boolean
        }
      })

      actorsThematics = mapThematics.map((x) => {
        return {
          ...x,
          isShown: myMapStore?.deserializedMapState?.layers?.actors?.includes(x.id) as boolean
        }
      })
    }
    myMapStore!.projectSubLayers = LayerService.initSubLayer(projectThematics as any)
    myMapStore!.actorSubLayers = LayerService.initSubLayer(actorsThematics as any)
  }

  static async setPlatformDataLayers() {
    const myMapStore = useMyMapStore()
    Object.values(ItemType)
      .filter((itemType) => itemType !== ItemType.RESOURCE)
      .forEach((itemType) => {
        this.setPlatformDataLayer(itemType)
      })
    if (myMapStore!.isMapAlreadyBeenMounted) {
      myMapStore!.setMapLayersOrderOnMapReMount()
    }
    myMapStore!.isMapAlreadyBeenMounted = true
  }

  static async setPlatformDataLayer(itemType: ItemType) {
    const myMapStore = useMyMapStore()
    if (myMapStore.myMap) {
      const geojson = this.getGeojsonPerItemType(itemType)
      const icon = new URL(`/src/assets/images/icons/map/${itemType}_icon.png`, import.meta.url).href

      // 1. Source avec clustering
      myMapStore.myMap.addSource(itemType, geojson, {
        cluster: true,
        clusterRadius: 50,
        clusterMaxZoom: 14
      })

      // 2. Image de l'icône
      await myMapStore.myMap.addImage(icon, itemType)

      // 3. Couches des clusters (cercle + compteur)
      myMapStore.myMap.addClusterLayers(itemType)

      // 4. Couche d'icônes, uniquement pour les points non regroupés
      const layout: maplibregl.LayerSpecification['layout'] = {
        'icon-image': itemType,
        'icon-size': 0.4,
        'icon-allow-overlap': true
      }
      myMapStore.myMap.addLayer(itemType, {
        layout,
        filter: ['!', ['has', 'point_count']]
      })

      myMapStore.myMap.listenToHoveredFeature(itemType)
    }
    return
  }

  static getGeojsonPerItemType(itemType: ItemType): PersistentGeoJSON {
    
    switch (itemType) {
      case ItemType.ACTOR:
        return MapService.getGeojson(this.filteredActors.value)
      case ItemType.PROJECT:
        return MapService.getGeojson(this.filteredProjects.value)
      default:
        // RESOURCE (ou toute future valeur d'ItemType) n'a pas de source pour l'instant
        return { type: 'FeatureCollection', features: [], isPersistent: false }
    }
  }

  // static filterByThematic(items: ThematicItem[], subLayers: Layer[]) {
  //   const activeThematics = subLayers.filter((l) => l.isShown).map((l) => l.name)
  //   const allShown = activeThematics.length === subLayers.length
  //   return items.filter((item) => {
  //     const thematics = item.thematics ?? []
  //     if (thematics.length === 0) return allShown
  //     return activeThematics.some((t) => thematics.includes(t as Thematic))
  //   })
  // }

  static filterByThematic(items: ThematicItem[], subLayers: Layer[]) {
    const activeThematics = subLayers.filter((l) => l.isShown).map((l) => l.name)
    return items.filter((item) => {
      const thematics = item.thematics ?? []
      return thematics.length > 0 && activeThematics.some((t) => thematics.includes(t as Thematic))
    })
  }
}