const COUNTRY_CODES = [
  'MA', 'DZ', 'TN', 'EG', 'SA', 'AE', 'QA', 'KW', 'BH', 'OM', 'JO', 'LB', 'TR', 'NG', 'GH', 'KE', 'ZA', 'SN', 'CI', 'CM',
  'FR', 'ES', 'PT', 'IT', 'DE', 'NL', 'BE', 'LU', 'CH', 'AT', 'GB', 'IE', 'SE', 'NO', 'DK', 'FI', 'PL', 'CZ', 'RO', 'GR',
  'US', 'CA', 'MX', 'BR', 'AR', 'CL', 'CO', 'PE', 'IN', 'PK', 'BD', 'CN', 'JP', 'KR', 'SG', 'MY', 'ID', 'PH', 'VN', 'TH', 'AU', 'NZ',
]

export function countries(): { code: string; name: string }[] {
  const dn = new Intl.DisplayNames(undefined, { type: 'region' })
  return COUNTRY_CODES.map((code) => ({ code, name: dn.of(code) ?? code })).sort((a, b) => a.name.localeCompare(b.name))
}

export function timezones(): string[] {
  try {
    return Intl.supportedValuesOf('timeZone')
  } catch {
    return ['UTC', 'Africa/Casablanca', 'Europe/Paris', 'Europe/London', 'America/New_York', 'Asia/Dubai']
  }
}

export function currencies(): { code: string; name: string }[] {
  const dn = new Intl.DisplayNames(undefined, { type: 'currency' })
  let codes: string[]
  try { codes = Intl.supportedValuesOf('currency') } catch { codes = ['MAD', 'EUR', 'USD', 'GBP', 'AED', 'SAR'] }
  return codes.map((code) => ({ code, name: dn.of(code) ?? code }))
}

export function browserTimezone(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
}
