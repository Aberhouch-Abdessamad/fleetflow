import L from 'leaflet'
import { useEffect, useRef } from 'react'
import { cn } from '@/lib/utils'

export interface MapMarker {
  id: string | number
  lat: number
  lng: number
  kind: 'vehicle' | 'pickup' | 'dropoff' | 'stop' | 'point'
  label?: string
  tone?: 'live' | 'stale' | 'primary' | 'accent' | 'danger' | 'muted'
  heading?: number | null
  popup?: string
  onClick?: () => void
}

const esc = (s: string) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!)

const COLORS: Record<NonNullable<MapMarker['tone']>, string> = {
  live: '#059669', stale: '#a1a1aa', primary: '#4f46e5', accent: '#0d9488', danger: '#dc2626', muted: '#71717a',
}

const INK = () => (document.documentElement.classList.contains('dark') ? '#fafafa' : '#18181b')

function icon(m: MapMarker): L.DivIcon {
  const c = m.kind === 'vehicle' && m.tone !== 'live' && m.tone !== 'stale' && m.tone !== 'danger' ? INK() : COLORS[m.tone ?? 'primary']
  if (m.kind === 'vehicle') {
    const rot = m.heading ?? 0
    return L.divIcon({
      className: 'ff-marker',
      iconSize: [30, 30],
      iconAnchor: [15, 15],
      html: `<div class="ff-veh" style="--c:${c}"><svg viewBox="0 0 24 24" style="transform:rotate(${rot}deg)"><path d="M12 3 19 20 12 16 5 20Z" fill="${c === '#fafafa' ? '#09090b' : 'white'}"/></svg>${m.tone === 'live' ? '<span class="ff-pulse"></span>' : ''}</div>${m.label ? `<span class="ff-veh-label">${esc(m.label)}</span>` : ''}`,
    })
  }
  const glyph = m.kind === 'pickup' ? 'P' : m.kind === 'dropoff' ? '' : m.label ?? ''
  return L.divIcon({
    className: 'ff-marker',
    iconSize: [28, 36],
    iconAnchor: [14, 34],
    html: `<div class="ff-pin" style="--c:${c}"><span>${esc(glyph)}</span></div>`,
  })
}

function tiles(): L.TileLayer {
  const dark = document.documentElement.classList.contains('dark')
  // CARTO/OpenStreetMap tiles, no API key (Leaflet).
  const sub = dark ? 'dark_all' : 'light_all'
  return L.tileLayer(`https://{s}.basemaps.cartocdn.com/${sub}/{z}/{x}/{y}{r}.png`, {
    maxZoom: 19, subdomains: 'abcd', attribution: '© OpenStreetMap contributors © CARTO',
  })
}

/**
 * Leaflet map with themed tiles. Markers/lines are re-rendered when props change;
 * the view only auto-fits on first data (or when `fitKey` changes) so live updates don't jump the map.
 */
export function FleetMap({ markers = [], line, onMapClick, className, fitKey, center = [33.5731, -7.5898], zoom = 11 }: {
  markers?: MapMarker[]
  line?: [number, number][]
  onMapClick?: (lat: number, lng: number) => void
  className?: string
  fitKey?: string | number
  center?: [number, number]
  zoom?: number
}) {
  const el = useRef<HTMLDivElement>(null)
  const map = useRef<L.Map | null>(null)
  const layer = useRef<L.LayerGroup | null>(null)
  const fitted = useRef<string | number | undefined>(undefined)
  const clickRef = useRef(onMapClick)
  clickRef.current = onMapClick

  useEffect(() => {
    if (!el.current || map.current) return
    const m = L.map(el.current, { zoomControl: false, attributionControl: true }).setView(center, zoom)
    L.control.zoom({ position: 'bottomright' }).addTo(m)
    tiles().addTo(m)
    layer.current = L.layerGroup().addTo(m)
    m.on('click', (e: L.LeafletMouseEvent) => clickRef.current?.(e.latlng.lat, e.latlng.lng))
    map.current = m
    const ro = new ResizeObserver(() => m.invalidateSize())
    ro.observe(el.current)
    return () => { ro.disconnect(); m.remove(); map.current = null }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    const m = map.current
    const g = layer.current
    if (!m || !g) return
    g.clearLayers()
    const bounds: L.LatLngExpression[] = []
    if (line && line.length > 1) {
      L.polyline(line, { color: '#4f46e5', weight: 3, opacity: 0.85 }).addTo(g)
      bounds.push(...line)
    }
    for (const mk of markers) {
      const marker = L.marker([mk.lat, mk.lng], { icon: icon(mk), riseOnHover: true })
      if (mk.popup) marker.bindPopup(mk.popup)
      if (mk.onClick) marker.on('click', mk.onClick)
      marker.addTo(g)
      bounds.push([mk.lat, mk.lng])
    }
    const key = fitKey ?? (bounds.length ? 'data' : undefined)
    if (bounds.length && fitted.current !== key) {
      fitted.current = key
      if (bounds.length === 1) m.setView(bounds[0], 14)
      else m.fitBounds(L.latLngBounds(bounds), { padding: [40, 40], maxZoom: 15 })
    }
  }, [markers, line, fitKey])

  return <div ref={el} className={cn('ff-map relative z-0 h-full min-h-64 w-full overflow-hidden rounded-xl bg-surface-3', className)} />
}

export { esc as escapeHtml }
