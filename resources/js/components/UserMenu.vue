<script setup lang="ts">
import { computed } from 'vue'
import { useColorMode } from '@vueuse/core'
import type { DropdownMenuItem } from '@nuxt/ui'
import { useAppConfig } from '../composables/useAppConfig'
import { router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps<{
  collapsed?: boolean
}>()

const colorMode = useColorMode()
const page = usePage()

// Use global app config
const appConfig = useAppConfig()

const colors = ['red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose']
const neutrals = ['slate', 'gray', 'zinc', 'neutral', 'stone']
const colorLabels: Record<string, string> = {
  red: 'Rojo',
  orange: 'Naranja',
  amber: 'Ámbar',
  yellow: 'Amarillo',
  lime: 'Lima',
  green: 'Verde',
  emerald: 'Esmeralda',
  teal: 'Verde azulado',
  cyan: 'Cian',
  sky: 'Celeste',
  blue: 'Azul',
  indigo: 'Índigo',
  violet: 'Violeta',
  purple: 'Morado',
  fuchsia: 'Fucsia',
  pink: 'Rosa',
  rose: 'Rosado',
  slate: 'Pizarra',
  gray: 'Gris',
  zinc: 'Zinc',
  neutral: 'Neutro',
  stone: 'Piedra'
}

const user = computed(() => {
  const authUser = (page.props as any).auth?.user as { name: string; email: string } | null
  return {
    name: authUser?.name || 'Usuario',
    avatar: {
      src: `https://ui-avatars.com/api/?name=${encodeURIComponent(authUser?.name || 'User')}&background=random`,
      alt: authUser?.name || 'Usuario'
    }
  }
})

const items = computed<DropdownMenuItem[][]>(() => {
  const authUser = (page.props as any).auth?.user as { name: string; email: string } | null

  return [[{
    type: 'label',
    label: user.value.name,
    avatar: user.value.avatar
  }, {
    type: 'label',
    label: authUser?.email || '',
    class: 'text-xs text-muted'
  }], [{
  label: 'Tema',
  icon: 'i-lucide-palette',
  children: [{
    label: 'Principal',
    slot: 'chip',
    chip: appConfig.value.ui.colors.primary,
    content: {
      align: 'center',
      collisionPadding: 16
    },
    children: colors.map(color => ({
      label: colorLabels[color],
      chip: color,
      slot: 'chip',
      checked: appConfig.value.ui.colors.primary === color,
      type: 'checkbox',
      onSelect: (e) => {
        e.preventDefault()

        appConfig.value.ui.colors.primary = color
      }
    }))
  }, {
    label: 'Neutro',
    slot: 'chip',
    chip: appConfig.value.ui.colors.neutral === 'neutral' ? 'old-neutral' : appConfig.value.ui.colors.neutral,
    content: {
      align: 'end',
      collisionPadding: 16
    },
    children: neutrals.map(color => ({
      label: colorLabels[color],
      chip: color === 'neutral' ? 'old-neutral' : color,
      slot: 'chip',
      type: 'checkbox',
      checked: appConfig.value.ui.colors.neutral === color,
      onSelect: (e) => {
        e.preventDefault()

        appConfig.value.ui.colors.neutral = color
      }
    }))
  }]
}, {
  label: 'Apariencia',
  icon: 'i-lucide-sun-moon',
  children: [{
    label: 'Claro',
    icon: 'i-lucide-sun',
    type: 'checkbox',
    checked: colorMode.value === 'light',
    onSelect(e: Event) {
      e.preventDefault()

      colorMode.value = 'light'
    }
  }, {
    label: 'Oscuro',
    icon: 'i-lucide-moon',
    type: 'checkbox',
    checked: colorMode.value === 'dark',
    onSelect(e: Event) {
      e.preventDefault()

      colorMode.value = 'dark'
    }
  }],
}, {
  label: 'Cerrar sesión',
  icon: 'i-lucide-log-out',
  onSelect: () => {
    router.post(route('logout'))
  }
}]]
})
</script>

<template>
  <UDropdownMenu
    :items="items"
    :content="{ align: 'center', collisionPadding: 12 }"
    :ui="{ content: collapsed ? 'w-48' : 'w-(--reka-dropdown-menu-trigger-width)' }"
  >
    <UButton
      v-bind="{
        ...user,
        label: collapsed ? undefined : user?.name,
        trailingIcon: collapsed ? undefined : 'i-lucide-chevrons-up-down'
      }"
      color="neutral"
      variant="ghost"
      block
      :square="collapsed"
      class="data-[state=open]:bg-elevated"
      :ui="{
        trailingIcon: 'text-dimmed'
      }"
    />

    <template #chip-leading="{ item }">
      <div class="inline-flex items-center justify-center shrink-0 size-5">
        <span
          class="rounded-full ring ring-bg bg-(--chip-light) dark:bg-(--chip-dark) size-2"
          :style="{
            '--chip-light': `var(--color-${(item as any).chip}-500)`,
            '--chip-dark': `var(--color-${(item as any).chip}-400)`
          }"
        />
      </div>
    </template>
  </UDropdownMenu>
</template>
