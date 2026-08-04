import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'
import { useToast } from './nuxt-compat'

const fieldLabels: Record<string, string> = {
  clienteId: 'cliente',
  cliente_id: 'cliente',
  vehiculoId: 'vehículo',
  vehiculo_id: 'vehículo',
  mecanicoIds: 'mecánicos',
  mecanico_ids: 'mecánicos',
  servicioIds: 'servicios solicitados',
  servicio_ids: 'servicios solicitados',
  fallaReportada: 'falla reportada',
  falla_reportada: 'falla reportada',
  diagnostico: 'diagnóstico técnico',
  pruebasRealizadas: 'pruebas realizadas',
  recomendaciones: 'recomendaciones',
  pagadoEn: 'fecha de pago',
  role_ids: 'roles'
}

function fieldLabel(field: string): string {
  const key = field.split('.')[0]
  if (fieldLabels[key]) return fieldLabels[key]

  return key
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replaceAll('_', ' ')
    .toLocaleLowerCase('es')
}

function validationMessage(field: string, message: string): string {
  const label = fieldLabel(field)
  const minimum = message.match(/(?:at least|minimo|mínimo) (\d+)/i)?.[1]
  const maximum = message.match(/(?:greater than|mayor que|máximo) (\d+)/i)?.[1]

  if (/required|obligatorio|requerido/i.test(message)) return `El campo «${label}» es obligatorio.`
  if (/valid email|correo electrónico válido/i.test(message)) return `Ingresa un correo electrónico válido en «${label}».`
  if (/already been taken|ya ha sido registrado|ya está en uso/i.test(message)) return `El valor de «${label}» ya está registrado.`
  if (/confirmation does not match|confirmación no coincide/i.test(message)) return `La confirmación de «${label}» no coincide.`
  if (/selected .* is invalid|seleccionado.*inválido/i.test(message)) return `Selecciona un valor válido para «${label}».`
  if (minimum) return `El campo «${label}» debe contener al menos ${minimum} caracteres o elementos.`
  if (maximum) return `El campo «${label}» no puede superar ${maximum} caracteres o elementos.`

  return message
}

export function useFlash() {
  const page = usePage()
  const toast = useToast()

  watch(
    () => page.props.flash,
    (flash: any) => {
      if (!flash) return

      if (flash.success) {
        toast.add({
          title: 'Operación confirmada',
          description: flash.success,
          color: 'green'
        })
      }

      if (flash.error) {
        toast.add({
          title: 'Error',
          description: flash.error,
          color: 'red'
        })
      }

      if (flash.info) {
        toast.add({
          title: 'Información',
          description: flash.info,
          color: 'blue'
        })
      }

      if (flash.warning) {
        toast.add({
          title: 'Advertencia',
          description: flash.warning,
          color: 'yellow'
        })
      }
    },
    { deep: true, immediate: true }
  )

  watch(
    () => page.props.errors,
    (errors: Record<string, string> | undefined) => {
      if (!errors || !Object.keys(errors).length) return

      const messages = Object.entries(errors)
        .map(([field, message]) => validationMessage(field, String(message)))
        .filter((message, index, all) => all.indexOf(message) === index)

      const description = messages.length === 1
        ? messages[0]
        : `No se pudo completar la operación. Corrige los campos indicados: ${messages.slice(0, 3).join(' ')}`

      toast.add({
        title: 'Revisa la información',
        description,
        color: 'red',
        duration: 7000
      })
    },
    { deep: true, immediate: true }
  )
}
