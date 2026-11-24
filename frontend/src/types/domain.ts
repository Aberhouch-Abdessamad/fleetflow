export type Id = number

export interface LatLng { latitude: number | null; longitude: number | null }
export interface Located { latitude: number; longitude: number; speed?: number | null; heading?: number | null; recorded_at: string; freshness: 'live' | 'stale' | 'none' }

export interface VehicleType { id: Id; name: string; code: string; required_license: string | null; default_capacity: string | null; capacity_unit: string; is_custom: boolean }

export type VehicleStatus = 'available' | 'assigned' | 'on_delivery' | 'under_maintenance' | 'out_of_service' | 'inactive'
export interface Vehicle {
  id: Id; registration_number: string; type?: VehicleType; vehicle_type_id: Id; make: string; model: string; year: number | null
  color: string | null; vin: string | null; fuel_type: string; capacity: string; capacity_unit: string; odometer: number
  status: VehicleStatus; image_url: string | null; insurance_provider: string | null; insurance_policy_number: string | null
  insurance_expiry_date: string | null; registration_expiry_date: string | null; last_service_date: string | null
  next_service_date: string | null; next_service_odometer: number | null; notes: string | null
  current_driver?: { id: Id; name: string; assignment_id: Id } | null; location: Located | null; archived: boolean; created_at: string
}

export interface Driver {
  id: Id; first_name: string; last_name: string; name: string; email: string | null; phone: string; photo_url: string | null
  license_type: string; license_expiry_date: string; license_valid: boolean; employment_date: string | null
  status: 'active' | 'inactive' | 'suspended'; availability: 'available' | 'on_duty' | 'off_duty' | 'on_leave'; has_account: boolean
  vehicle?: { id: Id; registration_number: string; label: string; assignment_id: Id } | null
  on_shift?: boolean; shift_started_at?: string | null; active_deliveries_count?: number
  license_number?: string; national_id?: string | null; address?: string | null; date_of_birth?: string | null
  emergency_contact?: string | null; emergency_phone?: string | null; notes?: string | null; created_at: string
}

export interface DocumentRecord { id: Id; document_type: string; document_number: string | null; file_url: string | null; original_name: string | null; issue_date: string | null; expiry_date: string | null; status: 'valid' | 'expiring' | 'expired'; created_at: string }

export interface CustomerAddress { id: Id; label: string | null; address_type: string; address: string; city: string; postal_code: string | null; latitude: number | null; longitude: number | null; contact_name: string | null; contact_phone: string | null; is_default: boolean }
export interface Customer { id: Id; name: string; email: string | null; phone: string | null; customer_type: string; tax_number: string | null; notes: string | null; addresses?: CustomerAddress[]; deliveries_count?: number; created_at: string }

export type DeliveryStatus = 'draft' | 'pending' | 'assigned' | 'accepted' | 'picked_up' | 'in_transit' | 'arrived' | 'delivered' | 'failed' | 'cancelled' | 'returned'
export type Priority = 'low' | 'normal' | 'high' | 'urgent'
export interface Delivery {
  id: Id; reference_number: string; external_source: string | null; external_reference: string | null
  status: DeliveryStatus; priority: Priority; customer?: { id: Id; name: string; phone: string | null; email: string | null } | null; customer_id: Id | null
  pickup: LatLng & { address: string; contact_name: string | null; contact_phone: string | null }
  dropoff: LatLng & { address: string; city: string | null }
  recipient: { name: string; phone: string | null; email: string | null }
  description: string | null; scheduled_pickup_at: string | null; scheduled_delivery_at: string | null
  actual_pickup_at: string | null; actual_delivery_at: string | null; assigned_at: string | null; accepted_at: string | null
  driver?: { id: Id; name: string; phone: string } | null
  vehicle?: { id: Id; registration_number: string; label: string; location: Located | null } | null
  route_id: Id | null; total_weight: string; distance_km: string | null; delivery_fee: string; cod_amount: string; cod_collected_amount: string | null
  requires_proof: boolean; requires_otp: boolean; delivery_notes: string | null; failure_reason: string | null; cancellation_reason: string | null
  customer_rating: number | null; rating_comment: string | null; on_time: boolean | null; is_late: boolean; allowed_transitions: DeliveryStatus[]
  items?: { id: Id; description: string; quantity: number; weight: string; dimensions: string | null; notes: string | null }[]
  timeline?: { id: Id; from: string | null; to: string; by: string | null; source: string; notes: string | null; latitude: number | null; longitude: number | null; at: string }[]
  assignments?: { id: Id; driver: string; driver_id: Id; vehicle: string | null; status: string; assigned_by: string | null; reason: string | null; created_at: string; responded_at: string | null }[]
  current_assignment?: { id: Id; status: string } | null
  proofs?: { id: Id; type: string; recipient_name: string | null; notes: string | null; photo_url: string | null; signature_url: string | null; latitude: number | null; longitude: number | null; created_at: string }[]
  invoiced?: boolean; tracking_url: string | null; created_at: string; updated_at: string
}

export interface RouteStop { id: Id; stop_order: number; stop_type: string; address: string; latitude: number | null; longitude: number | null; priority: Priority; status: string; estimated_arrival: string | null; actual_arrival: string | null; completed_at: string | null; delivery: { id: Id; reference_number: string; recipient: string; status: DeliveryStatus; weight: string; scheduled_delivery_at: string | null } | null }
export interface RoutePlan {
  id: Id; name: string; reference_number: string; route_date: string; status: 'planned' | 'in_progress' | 'completed' | 'cancelled'
  driver?: { id: Id; name: string } | null; vehicle?: { id: Id; registration_number: string; capacity: string } | null; driver_id: Id | null; vehicle_id: Id | null
  start: { address: string | null; latitude: number | null; longitude: number | null }; end: { address: string | null; latitude: number | null; longitude: number | null }
  planned_distance: string | null; actual_distance: string | null; estimated_duration: number | null; actual_duration: number | null
  distance_source: string | null; geometry: unknown; started_at: string | null; completed_at: string | null
  stops_count?: number; completed_stops?: number | null; total_weight?: number | null; stops?: RouteStop[]
  optimizations?: { provider: string; distance_before: string | null; distance_after: string | null; duration_before: number | null; duration_after: number | null; at: string }[]
}

export interface Maintenance { id: Id; vehicle?: { id: Id; registration_number: string; label: string }; vehicle_id: Id; maintenance_type: string; title: string; description: string | null; service_provider: string | null; scheduled_date: string | null; completed_date: string | null; odometer: number | null; cost: string; status: 'scheduled' | 'in_progress' | 'completed' | 'cancelled'; overdue: boolean; next_service_date: string | null; next_service_odometer: number | null; invoice_url: string | null; created_at: string }
export interface FuelRecord { id: Id; vehicle?: { id: Id; registration_number: string }; driver?: { id: Id; name: string } | null; fuel_type: string; quantity: string; unit_price: string; total_cost: string; odometer: number; full_tank: boolean; station_name: string | null; receipt_url: string | null; recorded_at: string }
export interface Expense { id: Id; category: string; description: string; amount: string; expense_date: string; status: 'pending' | 'approved' | 'rejected'; vehicle?: { id: Id; registration_number: string } | null; driver?: { id: Id; name: string } | null; delivery_id: Id | null; source: string | null; receipt_url: string | null; created_by?: string | null; created_by_id: Id | null; reviewed_by?: string | null; reviewed_at: string | null; rejection_reason: string | null; created_at: string }
export interface Invoice { id: Id; invoice_number: string; customer?: { id: Id; name: string; email: string | null; tax_number: string | null }; status: 'draft' | 'issued' | 'partially_paid' | 'paid' | 'void'; currency: string; subtotal: string; tax_rate: string; tax_amount: string; total_amount: string; amount_paid: string; balance: string; due_date: string | null; overdue: boolean; issued_at: string | null; notes: string | null; items?: { id: Id; delivery_id: Id | null; description: string; quantity: string; unit_price: string; total_price: string }[]; payments?: { id: Id; amount: string; payment_method: string; reference: string | null; paid_at: string }[]; created_at: string }
export interface AppNotification { id: string; type: string; data: { title: string; body?: string | null; category: string; tone: 'info' | 'success' | 'warning' | 'danger'; link?: string }; read: boolean; created_at: string }
