import { Eraser } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Button } from '@/components/ui'

/** Touch/mouse signature capture → PNG data URL. */
export function SignaturePad({ onChange }: { onChange: (dataUrl: string | null) => void }) {
  const ref = useRef<HTMLCanvasElement>(null)
  const drawing = useRef(false)
  const ink = useRef(false)
  const [empty, setEmpty] = useState(true)

  useEffect(() => {
    const c = ref.current!
    const ratio = window.devicePixelRatio || 1
    c.width = c.offsetWidth * ratio
    c.height = c.offsetHeight * ratio
    const ctx = c.getContext('2d')!
    ctx.scale(ratio, ratio)
    ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0d1631'
  }, [])

  const pos = (e: React.PointerEvent) => { const r = ref.current!.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top] as const }
  return (
    <div>
      <canvas ref={ref} className="h-40 w-full touch-none rounded-xl border-2 border-dashed border-border-strong bg-white"
        onPointerDown={(e) => { drawing.current = true; ref.current!.setPointerCapture(e.pointerId); const ctx = ref.current!.getContext('2d')!; const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y) }}
        onPointerMove={(e) => { if (!drawing.current) return; const ctx = ref.current!.getContext('2d')!; const [x, y] = pos(e); ctx.lineTo(x, y); ctx.stroke(); ink.current = true; if (empty) setEmpty(false) }}
        onPointerUp={() => { drawing.current = false; if (ink.current) onChange(ref.current!.toDataURL('image/png')) }} />
      <div className="mt-1.5 flex items-center justify-between text-xs text-muted">
        <span>{empty ? 'Ask the recipient to sign above' : 'Signature captured'}</span>
        <Button type="button" size="sm" variant="ghost" onClick={() => { const c = ref.current!; c.getContext('2d')!.clearRect(0, 0, c.width, c.height); setEmpty(true); ink.current = false; onChange(null) }}><Eraser /> Clear</Button>
      </div>
    </div>
  )
}
