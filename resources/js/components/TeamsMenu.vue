<script setup lang="ts">
import AutofixLogo from './AutofixLogo.vue'

defineProps<{
  collapsed?: boolean
}>()
</script>

<template>
  <div
    class="flex items-center gap-1 rounded-lg py-2"
    :class="collapsed ? 'justify-center' : ''"
  >
    <AutofixLogo
      class="ml-2 h-20 w-28 shrink-0 object-contain object-center"
    />
    <div v-if="!collapsed" class="flex min-w-0 flex-col justify-center">
      <p class="whitespace-nowrap text-xl font-bold tracking-tight">AUTOFIX</p>
      <p class="whitespace-nowrap text-xs text-muted">Taller automotriz</p>
    </div>
  </div>
</template>
