import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import LoginPage from '@/features/auth/pages/LoginPage'
import { useAuth } from '@/stores/auth'

describe('LoginPage', () => {
  it('validates before calling the API', async () => {
    const login = vi.fn()
    useAuth.setState({ login })
    render(<MemoryRouter><LoginPage /></MemoryRouter>)

    await userEvent.click(screen.getByRole('button', { name: /sign in/i }))

    expect(await screen.findByText('Email is required')).toBeInTheDocument()
    expect(screen.getByText('Password is required')).toBeInTheDocument()
    expect(login).not.toHaveBeenCalled()
  })

  it('submits credentials', async () => {
    const login = vi.fn().mockResolvedValue({ current_company: null })
    useAuth.setState({ login })
    render(<MemoryRouter><LoginPage /></MemoryRouter>)

    await userEvent.type(screen.getByLabelText('Email'), 'owner@fleetflow.test')
    await userEvent.type(screen.getByLabelText('Password'), 'password')
    await userEvent.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => expect(login).toHaveBeenCalledWith({ email: 'owner@fleetflow.test', password: 'password' }))
  })
})
