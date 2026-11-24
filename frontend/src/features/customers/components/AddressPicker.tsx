import { Crosshair, MapPin, Search } from 'lucide-react'
import { useState } from 'react'
import { FleetMap } from '@/components/map/FleetMap'
import { Button, Field, Input } from '@/components/ui'
import { useAsync } from '@/hooks/useAsync'
import { api } from '@/lib/api'
import { toApiError } from '@/lib/http'

export interface AddressValue { address: string; latitude: number | null; longitude: number | null; city?: string | null }

/**
 * Manual address entry + pin selection on the map. Search geocodes through the
 * backend provider (key-less OpenStreetMap by default); without one the user drops a pin.
 */
export function AddressPicker({ label, value, onChange, error, required }: { label: string; value: AddressValue; onChange: (v: AddressValue) => void; error?: string; required?: boolean }) {
  const cfg = useAsync(() => api.get<{ geocoding: boolean }>('/maps/config'), [])
  const [mapOpen, setMapOpen] = useState(false)
  const [results, setResults] = useState<{ label: string; latitude: number; longitude: number; city: string | null }[]>([])
  const [searching, setSearching] = useState(false)
  const [msg, setMsg] = useState<string | null>(null)

  const search = async () => {
    if (value.address.trim().length < 3) return
    setSearching(true); setMsg(null)
    try {
      const r = await api.get<typeof results>('/maps/geocode', { q: value.address })
      setResults(r)
      if (!r.length) setMsg('No matches — drop a pin on the map instead.')
    } catch (e) { setMsg(toApiError(e).message) } finally { setSearching(false) }
  }

  const hasPin = value.latitude !== null && value.longitude !== null
  return (
    <div className="space-y-2">
      <Field label={label} error={error} required={required} labelAside={
        <span className={hasPin ? 'inline-flex items-center gap-1 text-xs font-medium text-success' : 'text-xs text-subtle'}>
          {hasPin ? <><MapPin className="size-3" /> {value.latitude!.toFixed(5)}, {value.longitude!.toFixed(5)}</> : 'No map pin'}
        </span>}>
        <Input value={value.address} onChange={(e) => onChange({ ...value, address: e.target.value })} placeholder="Street, number, district, city"
          trailing={
            <span className="flex gap-0.5">
              {cfg.data?.geocoding && <Button type="button" size="icon-sm" variant="ghost" onClick={search} loading={searching} aria-label="Search address"><Search /></Button>}
              <Button type="button" size="icon-sm" variant="ghost" onClick={() => setMapOpen((o) => !o)} aria-label="Pick on map"><Crosshair /></Button>
            </span>
          } />
      </Field>
      {results.length > 0 && (
        <ul className="overflow-hidden rounded-lg border border-border bg-surface shadow-card">
          {results.map((r, i) => (
            <li key={i}><button type="button" className="w-full px-3 py-2 text-left text-sm hover:bg-surface-2" onClick={() => { onChange({ address: r.label, latitude: r.latitude, longitude: r.longitude, city: r.city }); setResults([]) }}>{r.label}</button></li>
          ))}
        </ul>
      )}
      {msg && <p className="text-xs text-muted">{msg}</p>}
      {mapOpen && (
        <div className="h-64 overflow-hidden rounded-xl border border-border">
          <FleetMap fitKey={hasPin ? 'pin' : undefined}
            markers={hasPin ? [{ id: 'p', lat: value.latitude!, lng: value.longitude!, kind: 'dropoff', tone: 'primary' }] : []}
            onMapClick={(lat, lng) => onChange({ ...value, latitude: Number(lat.toFixed(7)), longitude: Number(lng.toFixed(7)) })} />
        </div>
      )}
    </div>
  )
}
