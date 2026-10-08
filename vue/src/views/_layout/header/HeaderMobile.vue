<template>
  <div class="Header Header--mobile">
    <v-layout>
      <v-app-bar :elevation="2">
        <template v-slot:prepend>
          <v-app-bar-nav-icon
            color="main-blue"
            @click.stop="showMobileMenu = !showMobileMenu"
          ></v-app-bar-nav-icon>
        </template>
        <v-app-bar-title>
          <div class="d-flex align-center">
            <router-link
              :to="{ name: 'home' }"
              class="Header__bannerLink"
              @click="appStore.activeTab = 0"
            >
              <img loading="lazy" src="@/assets/images/Logo.png" class="Header__appLogo" />
              <span class="ml-4 text-main-blue">{{ $t('header.title') }}</span>
              <span class="ml-2 text-main-blue font-weight-bold">{{ $t('header.title2') }}</span>
            </router-link>
          </div>
        </v-app-bar-title>
      </v-app-bar>
    </v-layout>
    <div v-if="showMobileMenu" class="Header__menu">
      <div class="Header__menu__top">
        <img loading="lazy" src="@/assets/images/Logo.png" />
        <div class="Header__menu__closer" @click="showMobileMenu = !showMobileMenu">
          <v-icon icon="$close" />
        </div>
      </div>
      <img loading="lazy" src="@/assets/images/Frise.jpg" alt="" />
      <div class="Header__menu__content">
        <v-list lines="one" bg-color="light-yellow">
          <v-list-item
            v-for="(tab, index) in NavigationTabsService.getContent()"
            :key="index"
            :disabled="tab.disabled"
            @click="showMobileMenu = !showMobileMenu"
          >
            <RouterLink
              :to="tab.route"
              class="Header__tabsText"
              :class="{ 'Header__tabsText--disabled': tab.disabled }"
              :tabindex="tab.disabled ? -1 : undefined"
              :aria-disabled="tab.disabled ? 'true' : undefined"
            >
              <span
                :class="{ 'Header__tabsText--active': appStore.activeTab === tab.value }"
                @click="appStore.activeTab = tab.value"
                >{{ tab.name }}</span
              >
            </RouterLink>
          </v-list-item>
          <v-list-item>
            <LoginButton />
          </v-list-item>
        </v-list>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { NavigationTabsService } from '@/services/application/NavigationTabsService'
import { useApplicationStore } from '@/stores/applicationStore'
import { ref } from 'vue'
import LoginButton from './LoginButton.vue'

const appStore = useApplicationStore()
const showMobileMenu = ref(false)
const whatsappLink = `https://wa.me/${'+237652266618'.replace(/\D/g, '')}`
</script>

<style lang="scss">
.Header {
  &--mobile {
    height: $header-mobile-height;
    margin-top: $header-mobile-height;

    &::after {
      width: 100%;
      max-height: 30rem;
      height: 60vh;
    }
    .Header__bannerLink {
      display: flex;
      align-items: center;
      color: rgb(var(--v-theme-main-blue));
      text-decoration: none;
    }

    .Header__appLogo {
      border: 1px solid rgb(var(--v-theme-main-grey));
      border-radius: 50%;
      padding: 5px;
      height: 40px;
    }

    .Header__tabsText {
      color: rgb(var(--v-theme-main-blue));
      font-weight: 600;
      text-transform: none;
      text-decoration: none;

      &--active {
        color: rgb(var(--v-theme-main-red));
      }

      &--disabled {
        color: rgb(var(--v-theme-main-grey));
        cursor: not-allowed;
        pointer-events: none;
      }
    }

    .Header__menu {
      position: absolute;
      top: 0px;
      left: 0px;
      display: flex;
      flex-direction: column;
      width: 100%;
      height: 100vh;
      z-index: 2000;

      &__top {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
        background-color: white;
        height: 200px;

        img {
          height: 160px;
        }
      }

      &__closer {
        position: absolute;
        top: 20px;
        right: 20px;
        z-index: 2001;
        color: rgb(var(--v-theme-main-blue));
      }

      &__content {
        display: flex;
        flex-direction: column;
        width: 100%;
        background-color: rgb(var(--v-theme-light-yellow));
        flex-grow: 1;
        padding: $mobile-side-padding;
      }
    }
  }
}
</style>
