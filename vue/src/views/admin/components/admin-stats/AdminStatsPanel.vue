<!-- src/views/admin/components/admin-stats/AdminStatsPanel.vue -->
<template>
  <div class="AdminStatsPanel">
    <div class="AdminStatsPanel__header">
      <v-btn-toggle
        v-model="statsStore.days"
        class="AdminStatsPanel__range"
        mandatory
        density="compact"
        color="main-blue"
        variant="outlined"
        @update:model-value="statsStore.getStats($event)"
      >
        <v-btn :value="7">{{ $t('admin.stats.range7') }}</v-btn>
        <v-btn :value="30">{{ $t('admin.stats.range30') }}</v-btn>
        <v-btn :value="90">{{ $t('admin.stats.range90') }}</v-btn>
      </v-btn-toggle>
    </div>

    <transition name="fade" mode="out-in">
      <div class="AdminStatsPanel__content" v-if="hasData" key="content">
        <section class="AdminStatsPanel__section">
          <SectionBanner :text="$t('admin.stats.audience.title')" />
          <div class="AdminStatsPanel__grid">
            <!-- <DiverCityStaticKpi
              :label="$t('admin.stats.audience.totalViews')"
              :value="statsStore.audience?.totalViews ?? 0"
              icon="$eyeOutline"
            /> -->
            <DiverCityStaticKpi
              :label="$t('admin.stats.audience.uniqueVisitors')"
              :value="statsStore.audience?.uniqueVisitors ?? 0"
              icon="$accountGroup"
            />
            <DiverCityStaticKpi
              :label="$t('admin.stats.audience.averageSessionDuration')"
              :value="averageSessionLabel"
              icon="$clockOutline"
            />
          </div>
          <div class="AdminStatsPanel__chart">
            <StatsLineChart :series="audienceSeries" />
          </div>
          <div class="AdminStatsPanel__subTitle">
            {{ $t('admin.stats.audience.topPages') }} ({{ totalViews }})
          </div>
          <StatsTopList :items="topPagesItems" :empty-label="$t('admin.stats.empty')" />
        </section>

        <section class="AdminStatsPanel__section">
          <SectionBanner :text="$t('admin.stats.connections.title')" />
          <div class="AdminStatsPanel__grid">
            <DiverCityStaticKpi
              :label="$t('admin.stats.connections.totalConnections')"
              :value="statsStore.connections?.totalConnections ?? 0"
              icon="$loginVariant"
            />
            <DiverCityStaticKpi
              :label="$t('admin.stats.connections.activeUsers')"
              :value="statsStore.connections?.activeUsers ?? 0"
              icon="$accountCircle"
            />
          </div>
          <div class="AdminStatsPanel__chart">
            <StatsLineChart :series="connectionsSeries" />
          </div>

          <div class="AdminStatsPanel__subTitle">
            {{ $t('admin.stats.connections.topCountries') }}
          </div>
          <StatsCountryList
            :items="statsStore.connections?.topCountries ?? []"
            :empty-label="$t('admin.stats.empty')"
            :unknown-label="$t('admin.stats.connections.unknownCountry')"
          />

          <div class="AdminStatsPanel__subTitle">
            {{ $t('admin.stats.connections.origins') }}
          </div>
          <!-- Le tableau défile horizontalement dans son cadre au lieu de déborder de la page -->
          <div class="AdminStatsPanel__tableScroll">
            <StatsConnectionsTable
              :items="statsStore.connections?.recentConnections ?? []"
              :headers="connectionsTableHeaders"
              :empty-label="$t('admin.stats.empty')"
              :unknown-label="$t('admin.stats.connections.unknownCountry')"
            />
          </div>
        </section>

        <section class="AdminStatsPanel__section">
          <SectionBanner :text="$t('admin.stats.contentViews.title')" />
          <div class="AdminStatsPanel__contentGrid">
            <div>
              <div class="AdminStatsPanel__subTitle">
                {{ $t('admin.stats.contentViews.topActors') }}
              </div>
              <StatsTopList :items="topActorsItems" :empty-label="$t('admin.stats.empty')" />
            </div>
            <div>
              <div class="AdminStatsPanel__subTitle">
                {{ $t('admin.stats.contentViews.topProjects') }}
              </div>
              <StatsTopList :items="topProjectsItems" :empty-label="$t('admin.stats.empty')" />
            </div>
          </div>
        </section>
      </div>

      <div class="AdminStatsPanel__loading" v-else-if="statsStore.isLoading" key="loading">
        <v-progress-circular indeterminate color="main-blue" size="36" width="3" />
        <span class="AdminStatsPanel__loadingText">{{ $t('admin.stats.loading') }}</span>
      </div>

      <div class="AdminStatsPanel__empty" v-else key="empty">
        {{ $t('admin.stats.empty') }}
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import SectionBanner from '@/components/banners/SectionBanner.vue'
import DiverCityStaticKpi from '@/components/content/DiverCityStaticKpi.vue'
import StatsLineChart from '@/components/content/StatsLineChart.vue'
import StatsTopList from '@/components/content/StatsTopList.vue'
import StatsCountryList from '@/components/content/StatsCountryList.vue'
import StatsConnectionsTable from '@/components/content/StatsConnectionsTable.vue'
import { useStatsStore } from '@/stores/statsStore'
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'

const statsStore = useStatsStore()
const { t } = useI18n()

onMounted(() => {
  if (!statsStore.audience && !statsStore.connections && !statsStore.contentViews) {
    statsStore.getStats()
  }
})

const hasData = computed(
  () => !!statsStore.audience || !!statsStore.connections || !!statsStore.contentViews
)

const totalViews = computed(() => statsStore.audience?.totalViews ?? 0)

/**
 * Durée moyenne d'une session, formatée en "Xh Ymin" / "Xmin Ys" / "Xs".
 */
const averageSessionLabel = computed(() => {
  const seconds = statsStore.audience?.averageSessionSeconds ?? 0
  if (seconds <= 0) return '—'

  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const remainingSeconds = Math.round(seconds % 60)

  if (hours > 0) return `${hours} h ${minutes} min`
  if (minutes > 0) return `${minutes} min ${remainingSeconds} s`
  return `${remainingSeconds} s`
})

const connectionsTableHeaders = computed(() => ({
  country: t('admin.stats.connections.country'),
  ipAddress: t('admin.stats.connections.ipAddress'),
  user: t('admin.stats.connections.user'),
  date: t('admin.stats.connections.date')
}))

const audienceSeries = computed(
  () => statsStore.audience?.dailySeries.map((p) => ({ date: p.date, value: p.views })) ?? []
)

const connectionsSeries = computed(
  () => statsStore.connections?.dailySeries.map((p) => ({ date: p.date, value: p.count })) ?? []
)

const topPagesItems = computed(
  () =>
    statsStore.audience?.topPages.map((p) => ({ path: p.path, label: p.path, views: p.views })) ??
    []
)

const topActorsItems = computed(
  () =>
    statsStore.contentViews?.topActors.map((a) => ({
      path: a.path,
      label: a.slug,
      views: a.views
    })) ?? []
)

const topProjectsItems = computed(
  () =>
    statsStore.contentViews?.topProjects.map((p) => ({
      path: p.path,
      label: p.slug,
      views: p.views
    })) ?? []
)
</script>

<style lang="scss">
.AdminStatsPanel {
  display: flex;
  flex-direction: column;
  gap: 2.5rem;
  width: 100%;
  max-width: 100%;
  min-width: 0; // permet au panneau de rétrécir dans son parent flex/grid

  // Tous les enfants directs peuvent rétrécir : évite qu'un contenu large ne pousse la page
  > *,
  &__content > *,
  &__section > * {
    min-width: 0;
    max-width: 100%;
  }

  &__header {
    display: flex;
    justify-content: flex-end;
  }

  &__content {
    display: flex;
    flex-direction: column;
    gap: 2.5rem;
  }

  &__section {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    min-width: 0;
  }

  // Les colonnes s'adaptent toutes seules à la largeur disponible
  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr));
    gap: 2rem;

    > * {
      min-width: 0;
    }
  }

  &__contentGrid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr));
    gap: 2rem;

    > * {
      min-width: 0;
    }
  }

  // Graphiques : prennent la largeur du parent, jamais plus
  &__chart {
    width: 100%;
    min-width: 0;
    overflow: hidden;

    canvas,
    svg {
      max-width: 100%;
    }
  }

  // Tableau : défilement horizontal interne
  &__tableScroll {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;

    > * {
      min-width: 36rem; // garde les colonnes lisibles, le cadre défile en dessous
    }
  }

  &__subTitle {
    font-weight: 700;
    color: rgb(var(--v-theme-main-blue));
    overflow-wrap: anywhere;
    margin-bottom: 0.5rem;
  }

  &__loading,
  &__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 3rem 1rem;
    color: rgb(var(--v-theme-dark-grey));
    text-align: center;
  }

  &__loadingText {
    font-size: $font-size-sm;
  }

  // Chemins de pages / slugs longs : on coupe au lieu de déborder
  .StatsTopList,
  .StatsCountryList {
    overflow-wrap: anywhere;
    word-break: break-word;
  }
}

@media (max-width: $bp-md) {
  .AdminStatsPanel {
    gap: 1.75rem;

    &__content {
      gap: 1.75rem;
    }

    &__section {
      gap: 1.25rem;
    }

    &__grid,
    &__contentGrid {
      gap: 1rem;
    }

    // Le sélecteur de période prend toute la largeur
    &__header {
      justify-content: stretch;
    }

    &__range {
      width: 100%;

      .v-btn {
        flex: 1 1 0;
        min-width: 0;
        padding: 0 0.5rem;
      }
    }
  }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>