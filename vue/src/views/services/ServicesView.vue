<template>
  <section class="Services">
    <header class="Services__hero">
      <h1 class="Services__title">{{ $t('services.title') }}</h1>
      <p class="Services__subtitle">{{ $t('services.subtitle') }}</p>
    </header>

    <div class="Services__list">
      <ServiceCard
        v-for="(service, index) in SERVICES"
        :key="service.id"
        :service="service"
        :reverse="index % 2 === 1"
      />
    </div>
  </section>
</template>

<script setup lang="ts">
import ServiceCard from './components/ServiceCard.vue'
import { SERVICES } from './servicesData'
</script>

<style scoped>
.Services {
  max-width: 1200px;
  margin: 0 auto;
  padding: 48px 16px 64px;
}

.Services__hero {
  max-width: 720px;
  margin: 0 auto 48px;
  text-align: center;
}

.Services__title {
  margin: 0 0 12px;
  font-size: clamp(2rem, 4vw, 2.8rem);
  font-weight: 800;
  line-height: 1.1;
  color: rgb(var(--v-theme-main-blue));
}

.Services__title::after {
  content: '';
  display: block;
  width: 56px;
  height: 4px;
  margin: 16px auto 0;
  border-radius: 2px;
  background: rgb(var(--v-theme-main-yellow));
}

.Services__subtitle {
  margin: 0;
  font-size: 1.05rem;
  line-height: 1.6;
  color: rgb(var(--v-theme-dark-grey));
}

.Services__list {
  display: flex;
  flex-direction: column;
  gap: 32px;
}
</style>