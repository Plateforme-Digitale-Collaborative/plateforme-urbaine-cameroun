<template>
  <article
    class="ServiceCard"
    :class="{ 'ServiceCard--reverse': reverse, 'ServiceCard--disabled': !isAvailable }"
    :style="{ '--service-accent': `rgb(var(--v-theme-${service.color}))` }"
  >
    <div class="ServiceCard__visual">
      <template v-if="!logoFailed">
        <!-- Fond flouté : conteneur propre qui rogne le flou, pour que l'image, elle, puisse déborder -->
        <div class="ServiceCard__backdropWrap" aria-hidden="true">
          <div
            class="ServiceCard__backdrop"
            :style="{ backgroundImage: `url(${service.logo})` }"
          />
        </div>
        <!-- Image entière, qui sort du cadre au survol -->
        <img
          class="ServiceCard__logo"
          :src="service.logo"
          :alt="$t('services.card.logoAlt', { name: service.name })"
          @error="logoFailed = true"
        />
      </template>
      <span v-else class="ServiceCard__monogram" aria-hidden="true">{{ monogram }}</span>
    </div>

    <div class="ServiceCard__content">
      <h2 class="ServiceCard__title">{{ service.name }}</h2>
      <p class="ServiceCard__description">{{ $t(service.descriptionKey) }}</p>

      <a
        v-if="isAvailable"
        class="ServiceCard__link"
        :href="service.url"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="$t('services.card.accessAria', { name: service.name })"
      >
        {{ $t('services.card.access') }}
        <v-icon icon="$openInNew" size="18" class="ServiceCard__linkIcon" />
      </a>
      <span v-else class="ServiceCard__soon">{{ $t('services.card.soon') }}</span>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { PlatformService } from '../servicesData'

const props = defineProps<{
  service: PlatformService
  /** Inverse la disposition : image à droite, description à gauche */
  reverse?: boolean
}>()

const logoFailed = ref(false)
const isAvailable = computed(() => !!props.service.url)
const monogram = computed(() => props.service.name.trim().charAt(0).toUpperCase())
</script>

<style scoped>
.ServiceCard {
  position: relative;
  display: grid;
  grid-template-columns: 1fr 1fr;
  /* PAS d'overflow: hidden ici : l'image doit pouvoir sortir de la carte */
  background: #fff;
  border: 1px solid rgba(var(--v-theme-light-grey), 0.9);
  border-radius: 28px;
  transition:
    box-shadow 0.25s ease,
    border-color 0.25s ease;
}

.ServiceCard:hover {
  border-color: var(--service-accent);
  box-shadow: 0 18px 36px -16px rgba(0, 0, 0, 0.2);
}

/* La carte survolée passe au-dessus des cartes voisines */
.ServiceCard:has(.ServiceCard__visual:hover) {
  z-index: 10;
}

/* ---- Côté image ---- */
.ServiceCard__visual {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 340px;
  cursor: zoom-in;
  /* arrondi du côté extérieur (remplace l'overflow: hidden de la carte) */
  border-radius: 27px 0 0 27px;
  background: linear-gradient(
    135deg,
    color-mix(in srgb, var(--service-accent) 20%, white),
    color-mix(in srgb, var(--service-accent) 6%, white)
  );
}

/* Conteneur du fond flouté : c'est lui qui rogne, pas le bloc image */
.ServiceCard__backdropWrap {
  position: absolute;
  inset: 0;
  overflow: hidden;
  border-radius: inherit;
}

.ServiceCard__backdrop {
  position: absolute;
  inset: -24px;
  background-size: cover;
  background-position: center;
  filter: blur(22px) saturate(1.1);
  opacity: 0.55;
}

/* Image entière dans le cadre au repos */
.ServiceCard__logo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: center;
  z-index: 1;
  transform: scale(1);
  transform-origin: left center; /* le zoom s'étend vers le texte */
  transition:
    transform 0.5s cubic-bezier(0.22, 1, 0.36, 1),
    filter 0.5s ease;
  will-change: transform;
}

/* Au survol : l'image sort du cadre, passe au-dessus du texte et se détache avec une ombre */
.ServiceCard__visual:hover .ServiceCard__logo,
.ServiceCard__visual:focus-within .ServiceCard__logo {
  transform: scale(1.5);
  z-index: 20;
  filter: drop-shadow(0 24px 40px rgba(0, 0, 0, 0.35));
}

.ServiceCard__monogram {
  position: relative;
  z-index: 1;
  font-size: 6rem;
  font-weight: 800;
  line-height: 1;
  color: var(--service-accent);
}

/* ---- Côté texte ---- */
.ServiceCard__content {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-start;
  padding: 40px 48px;
}

.ServiceCard__title {
  margin: 0 0 16px;
  font-size: 1.9rem;
  font-weight: 800;
  line-height: 1.15;
  color: rgb(var(--v-theme-main-blue));
}

.ServiceCard__title::after {
  content: '';
  display: block;
  width: 48px;
  height: 4px;
  margin-top: 12px;
  border-radius: 2px;
  background: var(--service-accent);
}

.ServiceCard__description {
  margin: 0 0 28px;
  font-size: 1.1rem;
  line-height: 1.7;
  color: rgb(var(--v-theme-dark-grey));
}

.ServiceCard__link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 22px;
  font-weight: 700;
  text-decoration: none;
  border-radius: 999px;
  color: color-mix(in srgb, var(--service-accent) 55%, black);
  background: color-mix(in srgb, var(--service-accent) 16%, white);
  transition: background 0.25s ease;
}

.ServiceCard__link:hover,
.ServiceCard__link:focus-visible {
  background: color-mix(in srgb, var(--service-accent) 32%, white);
}

.ServiceCard__linkIcon {
  transition: transform 0.25s ease;
}

.ServiceCard__link:hover .ServiceCard__linkIcon {
  transform: translate(3px, -3px);
}

.ServiceCard__soon {
  padding: 6px 14px;
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: 999px;
  background: rgb(var(--v-theme-light-grey));
  color: rgb(var(--v-theme-dark-grey));
}

/* ---- Disposition croisée (desktop) ---- */
.ServiceCard--reverse .ServiceCard__visual {
  order: 2;
  border-radius: 0 27px 27px 0;
}

.ServiceCard--reverse .ServiceCard__logo {
  transform-origin: right center; /* le zoom s'étend vers le texte (à gauche) */
}

.ServiceCard--reverse .ServiceCard__content {
  order: 1;
}

/* ---- Mobile / tablette : une colonne, image toujours en haut ---- */
@media (max-width: 959px) {
  .ServiceCard {
    grid-template-columns: 1fr;
  }

  .ServiceCard__visual,
  .ServiceCard--reverse .ServiceCard__visual {
    order: 0;
    min-height: 260px;
    border-radius: 27px 27px 0 0;
  }

  .ServiceCard--reverse .ServiceCard__content {
    order: 0;
  }

  .ServiceCard__logo,
  .ServiceCard--reverse .ServiceCard__logo {
    transform-origin: center top;
  }

  .ServiceCard__content {
    padding: 28px 24px 32px;
  }

  .ServiceCard__title {
    font-size: 1.6rem;
  }
}

/* ---- Écrans tactiles : pas de survol, donc pas de zoom ---- */
@media (hover: none) {
  .ServiceCard__visual {
    cursor: default;
  }

  .ServiceCard__visual:hover .ServiceCard__logo {
    transform: none;
    filter: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ServiceCard,
  .ServiceCard__link,
  .ServiceCard__linkIcon,
  .ServiceCard__logo {
    transition: none;
  }
}
</style>