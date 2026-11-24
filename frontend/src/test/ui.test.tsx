import { render, screen } from '@testing-library/react'
import { Button, Field, Input, StatusBadge } from '@/components/ui'

describe('UI kit', () => {
  it('disables a loading button', () => {
    render(<Button loading>Save</Button>)
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled()
  })

  it('wires labels and errors to inputs', () => {
    render(<Field label="City" error="City is required"><Input /></Field>)
    const input = screen.getByLabelText('City')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByRole('alert')).toHaveTextContent('City is required')
  })

  it('humanises status values', () => {
    render(<StatusBadge status="in_transit" />)
    expect(screen.getByText('In transit')).toBeInTheDocument()
  })
})
