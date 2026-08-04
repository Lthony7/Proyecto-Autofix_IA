export const tiposDocumentoColombia = [
  { label: 'Cédula de ciudadanía', value: 'CC' },
  { label: 'Cédula de extranjería', value: 'CE' },
  { label: 'NIT', value: 'NIT' },
  { label: 'Pasaporte', value: 'PASAPORTE' }
]

export function documentoNumerico(tipo: string) {
  return tipo !== 'PASAPORTE'
}

export function longitudDocumento(tipo: string) {
  if (tipo === 'NIT') return 10
  if (tipo === 'PASAPORTE') return 20
  return 10
}

export function ayudaDocumento(tipo: string) {
  if (tipo === 'NIT') return '9 dígitos más el dígito de verificación, sin puntos ni guion.'
  if (tipo === 'PASAPORTE') return 'Entre 5 y 20 letras o números.'
  return 'Entre 6 y 10 dígitos.'
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
