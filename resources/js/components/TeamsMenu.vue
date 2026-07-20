<script setup lang="ts">
defineProps<{
  collapsed?: boolean
}>()
</script>

<template>
  <div
    class="flex items-center gap-3 rounded-lg px-2 py-2"
    :class="collapsed ? 'justify-center' : ''"
  >
    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-white">
      <i class="i-lucide-wrench text-lg" aria-hidden="true" />
    </div>
    <div v-if="!collapsed" class="min-w-0">
      <p class="truncate font-semibold">AUTOFIX IA</p>
      <p class="truncate text-xs text-muted">Gestión de taller</p>
    </div>
  </div>
</template>
