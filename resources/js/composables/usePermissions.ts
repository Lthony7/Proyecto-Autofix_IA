import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { SharedPageProps } from '../types'

export function usePermissions() {
  const page = usePage<SharedPageProps>()
  const permissions = computed(() => new Set(page.props.auth?.user?.permissions ?? []))

  const can = (permission: string) => permissions.value.has(permission)
  const canAny = (required: string[]) => required.some(can)

  return { can, canAny, permissions }
}
