import { createI18n } from 'vue-i18n'
import commonFR from '@/assets/translations/fr/common.json'
import actorsFR from '@/assets/translations/fr/actors.json'
import actorTeamFR from '@/assets/translations/fr/actorTeam.json'
import adminFR from '@/assets/translations/fr/admin.json'
import authFR from '@/assets/translations/fr/auth.json'
import projectsFR from '@/assets/translations/fr/projects.json'
import resourcesFR from '@/assets/translations/fr/resources.json'
import homeFR from '@/assets/translations/fr/home.json'
import myMapFR from '@/assets/translations/fr/myMap.json'
import routesFR from '@/assets/translations/fr/routes.json'
import qgisFR from '@/assets/translations/fr/qgisMapAndAtlas.json'
import divercityFR from '@/assets/translations/fr/divercity.json'
import servicesFR from '@/assets/translations/fr/services.json'

export const i18nInstance = createI18n({
  legacy: false,
  locale: 'fr',
  fallbackLocale: 'fr',
  messages: {
    fr: {
      ...commonFR,
      ...actorsFR,
      ...actorTeamFR,
      ...adminFR,
      ...authFR,
      ...projectsFR,
      ...homeFR,
      ...resourcesFR,
      ...myMapFR,
      ...routesFR,
      ...qgisFR,
      ...divercityFR,
      ...servicesFR
    }
  }
})

export const i18n = i18nInstance.global //Workaround for translate text outside components