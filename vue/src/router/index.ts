import { AdministrationPanels } from '@/models/enums/app/AdministrationPanels'
import { DialogKey } from '@/models/enums/app/DialogKey'
import { ProjectListDisplay } from '@/models/enums/app/ProjectListType'
import type { Actor } from '@/models/interfaces/Actor'
import { i18n } from '@/plugins/i18n'
import { pinia } from '@/plugins/index'
import { useActorsStore } from '@/stores/actorsStore'
import { useAdminStore } from '@/stores/adminStore'
import { useApplicationStore } from '@/stores/applicationStore'
import { useMyMapStore } from '@/stores/myMapStore'
import { useProjectStore } from '@/stores/projectStore'
import { useUserStore } from '@/stores/userStore'
import { useSpacesStore } from '@/stores/divercity/spacesStore'
import AdminComments from '@/views/admin/components/AdminComments.vue'
import AdminContent from '@/views/admin/components/AdminContent.vue'
import AdminMaps from '@/views/admin/components/AdminMaps.vue'
import AdminMembers from '@/views/admin/components/AdminMembers.vue'
import HomeView from '@/views/home/HomeView.vue'
import { createRouter, createWebHistory } from 'vue-router'
import { PageViewService } from '@/services/kpi/PageViewService'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    return savedPosition ? savedPosition : { el: '#app', top: 0 }
  },
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView
    },
    {
      name: 'ui',
      path: '/ui',
      beforeEnter: (to, from, next) =>
        import.meta.env.MODE === 'production' ? next({ name: 'home' }) : next(),
      component: () => import('@/views/_layout/ui/UiView.vue')
    },
    {
      path: `/${i18n.t('routes.actors')}`,
      name: 'actors',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/actors/ActorListView.vue')
      }
    },
    {
      path: `/${i18n.t('routes.actors')}/:slug`,
      name: 'actorProfile',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/actors/ActorSheetView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const actorsStore = useActorsStore(pinia)
        const actor: Actor | undefined = actorsStore.actors.find(
          (actor) => actor.slug === to.params.slug
        )

        if (actor?.id) {
          await actorsStore.setSelectedActor(actor.id, false)
        }
        next()
      }
    },
    {
      // Gestion de la page d'un acteur : équipe (point focal / administrateurs), validations, historique
      path: `/${i18n.t('routes.actors')}/:slug/${i18n.t('routes.actorManagement')}`,
      name: 'actorManagement',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/actors/ActorManagementView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const userStore = useUserStore(pinia)
        if (!userStore.loginCheck) {
          await userStore.checkAuthenticated()
        }
        if (!userStore.userIsLogged) {
          useApplicationStore(pinia).isLoading = false
          next({ path: '/' })
        } else {
          next()
        }
      }
    },
    {
      path: `/${i18n.t('routes.myActors')}`,
      name: 'myActors',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/member/MyActorsView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const userStore = useUserStore(pinia)
        if (!userStore.loginCheck) {
          await userStore.checkAuthenticated()
        }
        if (!userStore.userIsLogged) {
          useApplicationStore(pinia).isLoading = false
          next({ path: '/' })
        } else {
          next()
        }
      }
    },
    {
      path: `/${i18n.t('routes.projects')}`,
      name: 'projects',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/projects/ProjectListView.vue')
      },
      beforeEnter: (to, from, next) => {
        const projectStore = useProjectStore(pinia)
        projectStore.isProjectMapFullWidth = to.query.type === ProjectListDisplay.MAP ? true : false
        projectStore.activeProjectId = to.query.project ? to.query.project.toString() : null
        next()
      }
    },
    {
      path: `/${i18n.t('routes.projects')}/:slug`,
      name: 'projectPage',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/projects/ProjectSheetView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const projectStore = useProjectStore(pinia)
        await projectStore.loadProjectBySlug(to.params.slug)
        next()
      }
    },
    {
      path: `/${i18n.t('routes.resources')}`,
      name: 'resources',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/resources/ResourceListView.vue')
      }
    },
    {
      path: `/${i18n.t('routes.divercitySpace')}`,
      name: 'divercitySpace',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/divercity/SpaceSheetView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const spacesStore = useSpacesStore(pinia)
        try {
          await spacesStore.getMainSpace()
          await spacesStore.getPublicBookings()
        } catch (error) {
          console.error('Erreur lors du chargement de l\'espace DiverCity', error)
        }
        next()
      }
    },
    {
      path: `/${i18n.t('routes.divercitySpace')}/${i18n.t('routes.bookingForm')}`,
      name: 'divercitySpaceBooking',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/divercity/BookingFormView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const spacesStore = useSpacesStore(pinia)
        if (!spacesStore.mainSpace) {
          try {
            await spacesStore.getMainSpace()
          } catch (error) {
            console.error('Erreur lors du chargement de l\'espace DiverCity', error)
          }
        }
        next()
      }
    },
    {
      path: `/${i18n.t('routes.divercitySpace')}/${i18n.t('routes.divercitySpaceAvailability')}`,
      name: 'divercitySpaceAvailability',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/divercity/SpaceAvailabilityView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const spacesStore = useSpacesStore(pinia)
        if (!spacesStore.mainSpace) {
          try {
            await spacesStore.getMainSpace()
          } catch (error) {
            console.error('Erreur lors du chargement de l\'espace DiverCity', error)
          }
        }
        next()
      }
    },
    {
      path: `/${i18n.t('routes.divercitySpace')}/${i18n.t('routes.myBookings')}`,
      name: 'myDiverCityBookings',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/divercity/MyBookingsView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const userStore = useUserStore(pinia)
        if (!userStore.loginCheck) {
          await userStore.checkAuthenticated()
        }
        const applicationStore = useApplicationStore(pinia)
        if (!userStore.userIsLogged) {
          applicationStore.isLoading = false
          next({ path: '/' })
        } else {
          next()
        }
      }
    },
    {
      path: `/${i18n.t('routes.services')}`,
      name: 'services',
      component: () => import('@/views/services/ServicesView.vue')
    },
    {
      path: `/${i18n.t('routes.map')}`,
      name: 'map',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/map/MyMapView.vue')
      },
      beforeEnter: (to, from, next) => {
        const myMapStore = useMyMapStore()
        myMapStore.activeItemId = to.query.item ? to.query.item.toString() : null
        next()
      }
    },
    {
      path: `/${i18n.t('routes.myAccount')}`,
      name: 'userAccount',
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/member/MemberView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const userStore = useUserStore()
        if (!userStore.loginCheck) {
          await userStore.checkAuthenticated()
        }
        const applicationStore = useApplicationStore(pinia)
        if (!userStore.userIsLogged) {
          applicationStore.isLoading = false
          next({ path: '/' })
        } else {
          next()
        }
      }
    },
    {
      path: '/admin',
      name: 'admin',
      redirect: () => {
        const adminStore = useAdminStore(pinia)
        const userStore = useUserStore(pinia)
        if (userStore.userIsAdmin()) {
          adminStore.selectedAdminPanel = AdministrationPanels.MEMBERS
          return { name: 'adminUsers' }
        }
        adminStore.selectedAdminPanel = AdministrationPanels.DIVERCITY
        return { name: 'adminDiverCityBookings' }
      },
      component: () => {
        const applicationStore = useApplicationStore(pinia)
        applicationStore.isLoading = true
        return import('@/views/admin/AdminView.vue')
      },
      beforeEnter: async (to, from, next) => {
        const userStore = useUserStore(pinia)
        if (!userStore.loginCheck) {
          await userStore.checkAuthenticated()
        }
        if (!userStore.userIsAdmin() && !userStore.userIsDiverCitySpaceAdmin()) {
          next({ path: '/' })
        } else {
          next()
        }
      },
      children: [
        {
          path: 'users',
          name: 'adminUsers',
          component: AdminMembers
        },
        {
          name: 'adminDiverCitySpace',
          path: i18n.t('routes.divercitySpace'),
          component: () => import('@/views/admin/components/admin-divercity/SpaceManagementPanel.vue')
        },
        {
          name: 'adminDiverCityBookings',
          path: i18n.t('routes.adminDivercityBookings'),
          component: () => import('@/views/admin/components/admin-divercity/BookingsPanel.vue')
        },
        {
          name: 'adminDiverCityHighlights',
          path: i18n.t('routes.adminDivercityHighlights'),
          component: () => import('@/views/admin/components/admin-divercity/DiverCityHighlightsAdminPanel.vue')
        },
        {
          name: 'adminDiverCityKpis',
          path: i18n.t('routes.adminDivercityKpis'),
          component: () => import('@/views/admin/components/admin-divercity/KpisManagementPanel.vue')
        },
        {
          name: 'adminDiverCityBlockedPeriods',
          path: i18n.t('routes.adminDivercityBlockedPeriods'),
          component: () => import('@/views/admin/components/admin-divercity/BlockedPeriodsPanel.vue')
        },
        {
          name: 'adminStats',
          path: i18n.t('routes.adminStats'),
          component: () => import('@/views/admin/components/admin-stats/AdminStatsPanel.vue')
        },
        {
          path: 'content',
          name: 'adminContent',
          component: AdminContent,
          redirect: () => ({ name: 'adminActors' }),
          children: [
            {
              name: 'adminActors',
              path: 'actors',
              component: () => import('@/views/admin/components/admin-content/AdminActorsPanel.vue')
            },
            {
              name: 'adminProjects',
              path: 'projects',
              component: () =>
                import('@/views/admin/components/admin-content/AdminProjectsPanel.vue')
            },
            {
              name: 'adminResources',
              path: 'resources',
              component: () =>
                import('@/views/admin/components/admin-content/AdminResourcesPanel.vue')
            }
          ]
        },
        {
          path: 'maps',
          name: 'adminMaps',
          component: AdminMaps,
          redirect: () => ({ name: 'adminPredefinedMaps' }),
          children: [
            {
              name: 'adminPredefinedMaps',
              path: 'observatory',
              component: () => import('@/views/admin/components/admin-maps/AdminPredefinedMaps.vue')
            },
            {
              name: 'adminThematicMaps',
              path: 'catalogue',
              component: () => import('@/views/admin/components/admin-maps/AdminThematicMaps.vue')
            },
            {
              name: 'adminQgisMaps',
              path: 'data',
              component: () => import('@/views/admin/components/admin-maps/AdminQgisMaps.vue')
            }
          ]
        },
        {
          name: 'adminHighlights',
          path: 'highlights',
          component: () =>
            import('@/views/admin/components/admin-highlights/AdminHighlightsPanel.vue')
        },
        {
          name: 'adminComments',
          path: 'comments',
          component: AdminComments,
          redirect: () => ({ name: 'actorsComments' }),
          children: [
            {
              name: 'actorsComments',
              path: 'actorsComments',
              component: () => import('@/views/admin/components/admin-comments/ActorComments.vue')
            },
            {
              name: 'projectsComments',
              path: 'projectsComments',
              component: () => import('@/views/admin/components/admin-comments/ProjectComments.vue')
            },
            {
              name: 'resourcesComments',
              path: 'resourcesComments',
              component: () =>
                import('@/views/admin/components/admin-comments/ResourcesComments.vue')
            },
            {
              name: 'mapComments',
              path: 'mapComments',
              component: () => import('@/views/admin/components/admin-comments/MapComments.vue')
            }
          ]
        }
      ]
    }
  ]
})

router.beforeEach((to, from, next) => {
  const applicationStore = useApplicationStore()
  if (
    to.query.dialog !== undefined &&
    typeof to.query.dialog === 'string' &&
    Object.values(DialogKey).includes(to.query.dialog as unknown as DialogKey)
  ) {
    applicationStore.activeDialog = to.query.dialog as DialogKey
  } else {
    applicationStore.activeDialog = null
  }
  next()
})
declare global {
  interface Window {
    goatcounter: any
  }
}
// Add manual goat counter analytics for SPA https://www.goatcounter.com/help/spa
// @ts-ignore
if (import.meta.env.VITE_GOAT_COUNTER_NAMESPACE != null) {
  router.afterEach((to) => {
    if (window.goatcounter && typeof window.goatcounter.count === 'function') {
      window.goatcounter.count({
        path: to.fullPath
      })
    }
  })
}

// Tracking interne pour les KPIs d'audience de la PDC (indépendant de GoatCounter)
router.afterEach((to) => {
  PageViewService.logView(to.fullPath)
})

// Workaround for https://github.com/vitejs/vite/issues/11804
router.onError((err, to) => {
  if (err?.message?.includes?.('Failed to fetch dynamically imported module')) {
    if (!localStorage.getItem('vuetify:dynamic-reload')) {
      console.log('Reloading page to fix dynamic import error')
      localStorage.setItem('vuetify:dynamic-reload', 'true')
      location.assign(to.fullPath)
    } else {
      console.error('Dynamic import error, reloading page did not fix it', err)
    }
  } else {
    console.error(err)
  }
})

router.isReady().then(() => {
  localStorage.removeItem('vuetify:dynamic-reload')
})

export default router