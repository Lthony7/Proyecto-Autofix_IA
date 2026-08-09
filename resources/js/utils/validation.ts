export const tiposDocumentoEcuador = [
  { label: 'Cédula', value: 'CEDULA' },
  { label: 'RUC', value: 'RUC' },
  { label: 'Pasaporte', value: 'PASAPORTE' }
]

export function documentoNumerico(tipo: string) {
  return tipo !== 'PASAPORTE'
}

export function longitudDocumento(tipo: string) {
  if (tipo === 'RUC') return 13
  if (tipo === 'PASAPORTE') return 20
  return 10
}

export function ayudaDocumento(tipo: string) {
  if (tipo === 'RUC') return '13 dígitos, sin puntos ni guiones. Para persona natural, los 10 primeros dígitos corresponden a su cédula.'
  if (tipo === 'PASAPORTE') return 'Entre 5 y 20 letras o números.'
  return '10 dígitos.'
}

export function normalizarDocumento(value: string, tipo: string) {
  const normalizado = value.toUpperCase().replace(tipo === 'PASAPORTE' ? /[^A-Z0-9]/g : /\D/g, '')
  return normalizado.slice(0, longitudDocumento(tipo))
}

export function normalizarTelefono(value: string) {
  const tienePrefijo = value.trim().startsWith('+')
  const digitos = value.replace(/\D/g, '').slice(0, tienePrefijo ? 12 : 10)
  return tienePrefijo ? `+${digitos}` : digitos
}
