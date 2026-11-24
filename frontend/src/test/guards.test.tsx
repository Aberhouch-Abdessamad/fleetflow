import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { RequireAuth, RequireCompany } from '@/app/guards'
import { useAuth } from '@/stores/auth'
import type { User } from '@/types/api'

const baseUser: User = {
  id: 1, name: 'A', email: 'a@x.test', phone: null, email_verified: true, is_super_admin: false, status: 'active',
  current_company: null, roles: [], permissions: [],
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/login" element={<p>login page</p>} />
        <Route path="/onboarding" element={<p>onboarding page</p>} />
        <Route element={<RequireAuth />}>
          <Route element={<RequireCompany />}>
            <Route path="/app" element={<p>app home</p>} />
          </Route>
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

describe('route guards', () => {
  it('sends guests to login', () => {
    useAuth.setState({ status: 'guest', user: null })
    renderAt('/app')
    expect(screen.getByText('login page')).toBeInTheDocument()
  })

  it('sends users without a company to onboarding', () => {
    useAuth.setState({ status: 'authenticated', user: baseUser })
    renderAt('/app')
    expect(screen.getByText('onboarding page')).toBeInTheDocument()
  })

  it('blocks suspended workspaces', () => {
    useAuth.setState({
      status: 'authenticated',
      user: { ...baseUser, current_company: { id: 'c', name: 'Acme', status: 'suspended' } as User['current_company'] },
    })
    renderAt('/app')
    expect(screen.getByText('Acme is suspended')).toBeInTheDocument()
  })

  it('lets members of an active company in', () => {
    useAuth.setState({
      status: 'authenticated',
      user: { ...baseUser, current_company: { id: 'c', name: 'Acme', status: 'active' } as User['current_company'] },
    })
    renderAt('/app')
    expect(screen.getByText('app home')).toBeInTheDocument()
  })
})
