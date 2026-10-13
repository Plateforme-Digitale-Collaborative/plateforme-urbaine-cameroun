<template>
  <div class="TruncatedText">
    {{ text }}
    <v-tooltip activator="parent" :text="text" :location="location" />
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    text: string
    location?: 'top' | 'bottom' | 'start' | 'end'
  }>(),
  { location: 'top' }
)
</script>

<style scoped>
.TruncatedText {
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>