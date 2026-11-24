import { AxiosError, AxiosHeaders } from 'axios'
import { ApiError, toApiError } from '@/lib/http'

function axiosError(status: number, data: unknown) {
  const err = new AxiosError('fail', 'ERR', undefined, undefined, {
    status, data, statusText: '', headers: {}, config: { headers: new AxiosHeaders() },
  })
  return err
}

describe('toApiError', () => {
  it('normalises validation errors', () => {
    const e = toApiError(axiosError(422, { message: 'Invalid', code: 'validation_failed', errors: { email: ['Taken'] } }))
    expect(e).toBeInstanceOf(ApiError)
    expect(e.kind).toBe('validation')
    expect(e.firstError('email')).toBe('Taken')
  })

  it('recognises tenant errors before generic 403', () => {
    expect(toApiError(axiosError(403, { code: 'tenant_forbidden', message: 'x' })).kind).toBe('tenant')
    expect(toApiError(axiosError(403, { code: 'forbidden', message: 'x' })).kind).toBe('forbidden')
  })

  it('reports network failures', () => {
    const e = toApiError(new AxiosError('Network Error', 'ERR_NETWORK'))
    expect(e.kind).toBe('network')
  })
})
