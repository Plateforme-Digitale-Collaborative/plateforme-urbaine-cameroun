<template>
  <v-autocomplete
    v-model="selected"
    v-model:search="search"
    :items="items"
    item-title="fullName"
    item-value="id"
    return-object
    density="compact"
    variant="outlined"
    :loading="loading"
    :label="label"
    :placeholder="$t('actorTeam.team.searchPlaceholder')"
    :hint="$t('actorTeam.team.searchHint')"
    persistent-hint
    hide-no-data
    no-filter
    clearable
    :autofocus="autofocus"
    :error-messages="errorMessages"
    @update:model-value="emit('update:modelValue', selected)"
  >
    <template v-slot:no-data>
      <v-list-item :title="noDataText" />
    </template>
  </v-autocomplete>
</template>

<script setup lang="ts">
import type { AssignableUser } from '@/models/interfaces/ActorTeam'
import { i18n } from '@/plugins/i18n'
import { ActorTeamService } from '@/services/actors/ActorTeamService'
import { computed, ref, watch } from 'vue'

const props = defineProps<{
  actorId: string
  modelValue: AssignableUser | null
  label?: string
  autofocus?: boolean
  errorMessages?: string
}>()
const emit = defineEmits<{ (e: 'update:modelValue', value: AssignableUser | null): void }>()

const selected = ref<AssignableUser | null>(props.modelValue)
const search = ref('')
const items = ref<AssignableUser[]>([])
const loading = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined
let requestId = 0

const noDataText = computed(() =>
  search.value && search.value.trim().length >= 2
    ? i18n.t('actorTeam.team.noUserFound')
    : i18n.t('actorTeam.team.searchHint')
)

watch(
  () => props.modelValue,
  (value) => {
    selected.value = value
    if (!value) items.value = []
  }
)

watch(search, (query) => {
  clearTimeout(timer)
  const text = (query ?? '').trim()
  // Le texte du champ reprend le nom sélectionné : pas de nouvelle recherche dans ce cas.
  if (text.length < 2 || (selected.value && selected.value.fullName === text)) {
    if (text.length < 2) items.value = selected.value ? [selected.value] : []
    return
  }
  timer = setTimeout(async () => {
    const current = ++requestId
    loading.value = true
    try {
      const result = await ActorTeamService.searchAssignableUsers(props.actorId, text)
      if (current === requestId) items.value = result
    } catch {
      if (current === requestId) items.value = []
    } finally {
      if (current === requestId) loading.value = false
    }
  }, 300)
})
</script>