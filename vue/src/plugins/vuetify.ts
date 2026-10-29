/**
 * plugins/vuetify.ts
 *
 * Framework documentation: https://vuetifyjs.com`
 */

// Styles
// import '@mdi/font/css/materialdesignicons.css'
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg'
import { VDateInput } from 'vuetify/labs/VDateInput'
import 'vuetify/styles'

// Composables
import { createVuetify, type ThemeDefinition } from 'vuetify'

const pucCustomTheme: ThemeDefinition = {
  colors: {
    'main-blue': '#1c3b87',
    'bright-blue': '#111EF7',
    'light-blue': '#6176AB',
    'main-red': '#E83323',
    'main-yellow': '#F6CC47',
    'light-yellow': '#fdf5da',
    'main-grey': '#E0E0E0',
    'dark-grey': '#999999',
    'light-grey': '#F6F7FA',
    'main-green': '#2D6438'
  }
}

import {
  mdiAccountCircle,
  mdiAccountGroup,
  mdiArrowDownBoldHexagonOutline,
  mdiArrowLeft,
  mdiArrowRight,
  mdiArrowUpBoldHexagonOutline,
  mdiCalendar,
  mdiCheck,
  mdiCheckCircle,
  mdiChevronDown,
  mdiChevronLeft,
  mdiChevronRight,
  mdiCircleSmall,
  mdiClose,
  mdiCloseCircleOutline,
  mdiCommentText,
  mdiCommentTextOutline,
  mdiContacts,
  mdiContentCopy,
  mdiCrosshairs,
  mdiCrosshairsGps,
  mdiCrosshairsQuestion,
  mdiDatabaseArrowDown,
  mdiDatabaseSearch,
  mdiDeleteOffOutline,
  mdiDeleteOutline,
  mdiDotsHorizontal,
  mdiDotsVertical,
  mdiDownload,
  mdiDownloadOutline,
  mdiDrag,
  mdiEmailMultipleOutline,
  mdiEmailOpenOutline,
  mdiEmailOutline,
  mdiEmailPlusOutline,
  mdiEyeOffOutline,
  mdiEyeOutline,
  mdiFacebook,
  mdiFilter,
  mdiFolder,
  mdiFolderEditOutline,
  mdiFormatItalic,
  mdiHeart,
  mdiHeartOutline,
  mdiHelpCircleOutline,
  mdiLayers,
  mdiLayersOutline,
  mdiLogout,
  mdiMagnify,
  mdiMap,
  mdiMapMarkerOutline,
  mdiMapOutline,
  mdiMenuDown,
  mdiMinus,
  mdiOpacity,
  mdiOpenInNew,
  mdiPencil,
  mdiPencilOutline,
  mdiPhone,
  mdiPhoneOutline,
  mdiPlus,
  mdiPrinter,
  mdiRocketLaunch,
  mdiShareVariant,
  mdiShareVariantOutline,
  mdiStar,
  mdiStarOutline,
  mdiTrashCanOutline,
  mdiTune,
  mdiWhatsapp,
  mdiChartBar,
  mdiClockOutline,
  mdiEarth,
  mdiCheckboxMarkedCircle,
  mdiCheckboxBlankCircleOutline,
  mdiLoginVariant,


  mdiAccountOutline,
  mdiBriefcaseOutline,
  mdiDomain,
  mdiCalendarOutline,
  mdiAccountGroupOutline,
  mdiTextBoxOutline,
  mdiTagOutline,
  mdiInformationOutline,
  mdiPaperclip,
  mdiFileDocumentOutline,
  mdiCheckCircleOutline,
  mdiAccountPlusOutline,
  mdiAccountStarOutline,
  mdiShieldAccountOutline,
  mdiSwapHorizontal,
  mdiAlertCircleOutline,
  mdiClipboardCheckOutline
} from '@mdi/js'
// https://vuetifyjs.com/en/introduction/why-vuetify/#feature-guides
export default createVuetify({
  icons: {
    defaultSet: 'mdi',
    aliases: {
      ...aliases,
      formatItalic: mdiFormatItalic,
      crosshairsQuestion: mdiCrosshairsQuestion,
      phone: mdiPhone,
      folder: mdiFolder,
      commentText: mdiCommentText,
      commentTextOutline: mdiCommentTextOutline,
      emailOpenOutline: mdiEmailOpenOutline,
      deleteOffOutline: mdiDeleteOffOutline,
      star: mdiStar,
      starOutline: mdiStarOutline,
      contentCopy: mdiContentCopy,
      facebook: mdiFacebook,
      whatsapp: mdiWhatsapp,
      emailMultipleOutline: mdiEmailMultipleOutline,
      layers: mdiLayers,
      heart: mdiHeart,
      heartOutline: mdiHeartOutline,
      eyeOutline: mdiEyeOutline,
      eyeOffOutline: mdiEyeOffOutline,
      filter: mdiFilter,
      drag: mdiDrag,
      downloadOutline: mdiDownloadOutline,
      download: mdiDownload,
      shareVariant: mdiShareVariant,
      shareVariantOutline: mdiShareVariantOutline,
      folderEditOutline: mdiFolderEditOutline,
      databaseSearch: mdiDatabaseSearch,
      logout: mdiLogout,
      tune: mdiTune,
      dotsHorizontal: mdiDotsHorizontal,
      deleteOutline: mdiDeleteOutline,
      helpCircleOutline: mdiHelpCircleOutline,
      accountCircle: mdiAccountCircle,
      accountGroup: mdiAccountGroup,
      clockOutline: mdiClockOutline,
      earth: mdiEarth,
      emailOutline: mdiEmailOutline,
      emailPlusOutline: mdiEmailPlusOutline,
      minus: mdiMinus,
      printer: mdiPrinter,
      phoneOutline: mdiPhoneOutline,
      opicity: mdiOpacity,
      magnify: mdiMagnify,
      menuDown: mdiMenuDown,
      arrowRight: mdiArrowRight,
      arrowLeft: mdiArrowLeft,
      chevronLeft: mdiChevronLeft,
      chevronRight: mdiChevronRight,
      chevronDown: mdiChevronDown,
      check: mdiCheck,
      checkCircle: mdiCheckCircle,
      close: mdiClose,
      closeCircleOutline: mdiCloseCircleOutline,
      pencilOutline: mdiPencilOutline,
      pencil: mdiPencil,
      dotsVertical: mdiDotsVertical,
      mapOutline: mdiMapOutline,
      map: mdiMap,
      layersOutline: mdiLayersOutline,
      arrowUpBoldHexagonOutline: mdiArrowUpBoldHexagonOutline,
      arrowDownBoldHexagonOutline: mdiArrowDownBoldHexagonOutline,
      contacts: mdiContacts,
      rocketLaunch: mdiRocketLaunch,
      databaseArrowDown: mdiDatabaseArrowDown,
      openInNew: mdiOpenInNew,
      calendar: mdiCalendar,
      mapMarkerOutline: mdiMapMarkerOutline,
      plus: mdiPlus,
      circleSmall: mdiCircleSmall,
      crosshairsGps: mdiCrosshairsGps,
      crosshairs: mdiCrosshairs,
      trashCanOutlines: mdiTrashCanOutline,
      chartBar: mdiChartBar,
      checkboxMarkedCircle: mdiCheckboxMarkedCircle,
      checkboxBlankCircleOutline: mdiCheckboxBlankCircleOutline,
      loginVariant: mdiLoginVariant,


      accountOutline: mdiAccountOutline,
      briefcaseOutline: mdiBriefcaseOutline,
      domain: mdiDomain,
      calendarOutline: mdiCalendarOutline,
      accountGroupOutline: mdiAccountGroupOutline,
      textBoxOutline: mdiTextBoxOutline,
      tagOutline: mdiTagOutline,
      informationOutline: mdiInformationOutline,
      paperclip: mdiPaperclip,
      fileDocumentOutline: mdiFileDocumentOutline,
      checkCircleOutline: mdiCheckCircleOutline,
      accountPlusOutline: mdiAccountPlusOutline,
      accountStarOutline: mdiAccountStarOutline,
      shieldAccountOutline: mdiShieldAccountOutline,
      swapHorizontal: mdiSwapHorizontal,
      alertCircleOutline: mdiAlertCircleOutline,
      clipboardCheckOutline: mdiClipboardCheckOutline
    },
    sets: {
      mdi
    }
  },
  components: {
    VDateInput
  },
  display: {
    mobileBreakpoint: 'lg',
    thresholds: {
      xs: 0,
      sm: 576,
      md: 768,
      lg: 992,
      xl: 1100
    }
  },
  theme: {
    defaultTheme: 'pucCustomTheme',
    themes: {
      pucCustomTheme
    }
  },
  defaults: {
    VBtn: {
      variant: 'flat',
      style: [
        {
          textTransform: 'none',
          fontWeight: 'bold',
          letterSpacing: '.045rem'
        }
      ]
    },
    VTextField: {
      hideDetails: 'auto'
    },
    VTextarea: {
      hideDetails: 'auto'
    },
    VSelect: {
      hideDetails: 'auto'
    },
    VAutocomplete: {
      hideDetails: 'auto'
    },
    VPagination: {
      rounded: 'circle',
      color: 'main-blue',
      activeColor: 'main-yellow'
    },
    VTabs: {
      height: '38px'
    },
    VBreadcrumbs: {
      style: [
        {
          padding: '.75rem 0',
          fontSize: '.875rem'
        }
      ]
    },
    VFieldLabel: {
      style: [
        {
          opacity: '1'
        }
      ]
    }
  }
})