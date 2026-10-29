<template>
  <div class="ActorChangeRequestsPanel">
    <v-chip-group v-model="filter" mandatory selected-class="text-main-red" class="mb-4">
      <v-chip v-for="option in filters" :key="option" :value="option" filter variant="outlined">
        {{ $t('actorTeam.requests.filter.' + option) }}
      </v-chip>
    </v-chip-group>

    <div v-if="teamStore.changeRequests.length === 0" class="ActorChangeRequestsPanel__empty">
      {{
        filter === ActorChangeStatus.PENDING
          ? $t('actorTeam.requests.emptyPending')
          : $t('actorTeam.requests.empty')
      }}
    </div>

    <v-card
      v-for="request in teamStore.changeRequests"
      :key="request.id"
      variant="outlined"
      class="ActorChangeRequestsPanel__item"
    >
      <div class="ActorChangeRequestsPanel__main">
        <div class="d-flex flex-wrap align-center ga-2">
          <v-chip size="small" color="main-blue" variant="tonal">
            {{ $t('actorTeam.requests.target.' + request.targetType) }}
          </v-chip>
          <v-chip size="small" variant="tonal">
            {{ $t('actorTeam.requests.operation.' + request.operation) }}
          </v-chip>
          <v-chip size="small" :color="statusColor(request.status)" variant="flat">
            {{ $t('actorTeam.requests.status.' + request.status) }}
          </v-chip>
        </div>
        <div class="font-weight-bold mt-2">{{ request.targetLabel }}</div>
        <div class="text-caption text-medium-emphasis">
          {{ $t('actorTeam.requests.requestedBy', { name: request.requestedByName ?? '?' }) }}
          {{
            $t('actorTeam.requests.requestedAt', {
              date: localizeDate(request.requestedAt),
              time: localizeTime(request.requestedAt)
            })
          }}
        </div>
        <div class="text-caption text-medium-emphasis" v-if="request.reviewedAt">
          {{
            $t('actorTeam.requests.reviewedBy', {
              name: request.reviewedByName ?? '?',
              date: localizeDate(request.reviewedAt)
            })
          }}
          <span v-if="request.reviewComment"> — « {{ request.reviewComment }} »</span>
        </div>
      </div>
      <v-btn
        variant="outlined"
        color="main-blue"
        prepend-icon="$eyeOutline"
        @click="open(request)"
      >
        {{
          canReview && request.status === ActorChangeStatus.PENDING
            ? $t('actorTeam.requests.review')
            : $t('actorTeam.requests.details')
        }}
      </v-btn>
    </v-card>

    <!-- Détail / décision -->
    <v-dialog v-model="dialog" max-width="44rem" scrollable>
      <v-card v-if="selected" class="pa-2">
        <v-card-title>
          {{ $t('actorTeam.requests.operation.' + selected.operation) }} —
          {{ $t('actorTeam.requests.target.' + selected.targetType) }} :
          {{ selected.targetLabel }}
        </v-card-title>
        <v-card-text>
          <div class="text-caption text-medium-emphasis mb-4">
            {{ $t('actorTeam.requests.requestedBy', { name: selected.requestedByName ?? '?' }) }}
            {{
              $t('actorTeam.requests.requestedAt', {
                date: localizeDate(selected.requestedAt),
                time: localizeTime(selected.requestedAt)
              })
            }}
          </div>

          <!-- Ajout : l'élément existe mais n'est pas public -->
          <template v-if="selected.operation === ActorChangeOperation.CREATE">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
              {{ $t('actorTeam.requests.newItemHelp') }}
            </v-alert>
            <h5 class="ActorChangeRequestsPanel__subtitle">
              {{ $t('actorTeam.requests.preview') }}
            </h5>
            <div v-if="previewLoading" class="py-4"><v-progress-circular indeterminate /></div>
            <div v-else-if="preview" class="ActorChangeRequestsPanel__preview">
              <div class="font-weight-bold">{{ preview.name }}</div>
              <div v-if="preview.description" v-html="preview.description" />
              <div v-if="preview.link">
                <a :href="preview.link" target="_blank" rel="noopener">{{ preview.link }}</a>
              </div>
            </div>
            <p v-else class="text-medium-emphasis">{{ $t('actorTeam.requests.noPreview') }}</p>
          </template>

          <!-- Modification : liste des champs proposés -->
          <template v-else>
            <h5 class="ActorChangeRequestsPanel__subtitle">
              {{ $t('actorTeam.requests.changedFields') }}
            </h5>
            <v-table density="comfortable">
              <tbody>
                <tr v-for="(value, key) in selected.payload ?? {}" :key="key">
                  <td class="font-weight-bold ActorChangeRequestsPanel__fieldName">
                    {{ fieldLabel(String(key)) }}
                  </td>
                  <td class="ActorChangeRequestsPanel__fieldValue">
                    <span v-html="formatValue(value)" />
                  </td>
                </tr>
              </tbody>
            </v-table>
          </template>

          <template v-if="selected.reviewComment && selected.status !== ActorChangeStatus.PENDING">
            <h5 class="ActorChangeRequestsPanel__subtitle mt-4">
              {{ $t('actorTeam.requests.comment') }}
            </h5>
            <p>{{ selected.reviewComment }}</p>
          </template>

          <template v-if="canReview && selected.status === ActorChangeStatus.PENDING">
            <v-textarea
              v-model="comment"
              class="mt-4"
              variant="outlined"
              rows="2"
              auto-grow
              :label="$t('actorTeam.requests.comment')"
              :placeholder="$t('actorTeam.requests.commentPlaceholder')"
              :error-messages="commentError"
            />
          </template>
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="dialog = false">{{ $t('actorTeam.requests.close') }}</v-btn>
          <v-spacer />
          <template v-if="canReview && selected.status === ActorChangeStatus.PENDING">
            <v-btn
              color="main-red"
              variant="outlined"
              prepend-icon="$closeCircleOutline"
              :loading="teamStore.loading"
              @click="decide(ActorChangeStatus.REJECTED)"
            >
              {{ $t('actorTeam.requests.reject') }}
            </v-btn>
            <v-btn
              color="main-green"
              variant="flat"
              prepend-icon="$checkCircle"
              :loading="teamStore.loading"
              @click="decide(ActorChangeStatus.APPROVED)"
            >
              {{ $t('actorTeam.requests.approve') }}
            </v-btn>
          </template>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import {
  ActorChangeOperation,
  ActorChangeStatus,
  type ActorChangeTargetType
} from '@/models/enums/actors/ActorTeam'
import type { Actor } from '@/models/interfaces/Actor'
import type { ActorChangeRequest } from '@/models/interfaces/ActorTeam'
import { i18n } from '@/plugins/i18n'
import { ActorTeamService } from '@/services/actors/ActorTeamService'
import { localizeDate, localizeTime } from '@/services/utils/UtilsService'
import { useActorTeamStore } from '@/stores/actorTeamStore'
import { useUserStore } from '@/stores/userStore'
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps<{ actor: Actor }>()

const teamStore = useActorTeamStore()
const userStore = useUserStore()

/** Point focal / admin PDC : peut approuver ou refuser. Un administrateur de page ne fait que consulter ses demandes. */
const canReview = computed(() => userStore.userCanValidateFor(props.actor))

type Filter = ActorChangeStatus | 'all'
const filters: Filter[] = [
  ActorChangeStatus.PENDING,
  ActorChangeStatus.APPROVED,
  ActorChangeStatus.REJECTED,
  'all'
]
const filter = ref<Filter>(ActorChangeStatus.PENDING)

const dialog = ref(false)
const selected = ref<ActorChangeRequest | null>(null)
const comment = ref('')
const commentError = ref('')
const preview = ref<Record<string, any> | null>(null)
const previewLoading = ref(false)

const reload = () =>
  teamStore.loadChangeRequests(props.actor.id, filter.value === 'all' ? undefined : filter.value)

onMounted(reload)
watch(filter, reload)

function statusColor(status: ActorChangeStatus) {
  return {
    [ActorChangeStatus.PENDING]: 'main-yellow',
    [ActorChangeStatus.APPROVED]: 'main-green',
    [ActorChangeStatus.REJECTED]: 'main-red'
  }[status]
}

async function open(request: ActorChangeRequest) {
  selected.value = request
  comment.value = ''
  commentError.value = ''
  preview.value = null
  dialog.value = true
  if (request.operation === ActorChangeOperation.CREATE) {
    previewLoading.value = true
    preview.value = await ActorTeamService.getTargetPreview(
      request.targetType as ActorChangeTargetType,
      request.targetId
    )
    previewLoading.value = false
  }
}

async function decide(decision: ActorChangeStatus.APPROVED | ActorChangeStatus.REJECTED) {
  if (!selected.value) return
  commentError.value = ''
  if (decision === ActorChangeStatus.REJECTED && !comment.value.trim()) {
    commentError.value = i18n.t('actorTeam.requests.commentRequired')
    return
  }
  const ok = await teamStore.review(selected.value, decision, comment.value.trim())
  if (ok) {
    dialog.value = false
    await reload()
  }
}

/** Libellé d'un champ de formulaire ; à défaut, le nom technique du champ. */
function fieldLabel(key: string): string {
  const path = `actorTeam.fields.${key}`
  return i18n.te(path) ? i18n.t(path) : key
}

const escapeHtml = (text: string) =>
  text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

/** Représentation lisible (et échappée) d'une valeur du payload. */
function formatValue(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return `<em>${i18n.t('actorTeam.requests.emptyValue')}</em>`
  }
  if (Array.isArray(value)) {
    const printable = value.filter((v) => typeof v === 'string' || typeof v === 'number')
    if (printable.length === value.length && value.length <= 5) {
      return escapeHtml(printable.join(', '))
    }
    return escapeHtml(i18n.t('actorTeam.requests.items', { count: value.length }))
  }
  if (typeof value === 'object') {
    const record = value as Record<string, unknown>
    return escapeHtml(String(record.name ?? record['@id'] ?? JSON.stringify(value)))
  }
  const text = String(value)
  // Les descriptions sont du HTML riche : on les affiche en texte brut, tronqué.
  const plain = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
  return escapeHtml(plain.length > 400 ? plain.slice(0, 400) + '…' : plain)
}
</script>

<style lang="scss">
.ActorChangeRequestsPanel {
  &__empty {
    padding: 1.5rem;
    border-radius: 4px;
    background-color: rgb(var(--v-theme-light-grey));
    color: rgb(var(--v-theme-dark-grey));
  }

  &__item {
    display: flex;
    flex-flow: row wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
  }

  &__subtitle {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: rgb(var(--v-theme-main-blue));
  }

  &__fieldName {
    width: 35%;
    vertical-align: top;
  }

  &__fieldValue {
    word-break: break-word;
  }

  &__preview {
    padding: 1rem;
    background-color: rgb(var(--v-theme-light-grey));
    border-radius: 4px;
  }
}
</style>