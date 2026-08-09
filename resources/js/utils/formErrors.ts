import { nextTick } from 'vue'

export async function focusInvalidField(field?: string) {
  await nextTick()
  window.setTimeout(() => {
    const container = field ? document.querySelector<HTMLElement>(`[data-field="${field}"]`) : null
    const target = container ?? document.querySelector<HTMLElement>('[aria-invalid="true"]')?.closest<HTMLElement>('[data-field], .space-y-2') ?? document.querySelector<HTMLElement>('[aria-invalid="true"]')
    if (!target) return
    target.scrollIntoView({ behavior: 'smooth', block: 'center' })
    const control = target.matches('input, textarea, button, select') ? target : target.querySelector<HTMLElement>('input, textarea, button, select, [tabindex]:not([tabindex="-1"])')
    control?.focus({ preventScroll: true })
  }, 30)
}
