<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryProof;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\Expense;
use App\Models\FuelRecord;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\Tenancy\TenantContext;
use App\Support\Uploads;
use Illuminate\Http\Request;

/**
 * Authorised downloads for private tenant files. Model lookups go through the
 * tenant scope, so another company's file id is a 404; then a permission check.
 */
class FileController extends Controller
{
    public function __invoke(Request $request, string $kind, int $id)
    {
        $user = $request->user();
        $companyId = app(TenantContext::class)->id();

        [$path, $allowed] = match ($kind) {
            'vehicle-image' => [Vehicle::withTrashed()->findOrFail($id)->image_path, $user->can('vehicles.view') || $user->can('deliveries.view_own')],
            'vehicle-document' => [VehicleDocument::findOrFail($id)->file_path, $user->can('vehicles.view')],
            'driver-photo' => [($d = Driver::withTrashed()->findOrFail($id))->photo_path, $user->can('drivers.view') || $d->user_id === $user->id],
            'driver-document' => [($d = DriverDocument::with('driver')->findOrFail($id))->file_path, $user->can('drivers.view_private') || $d->driver?->user_id === $user->id],
            'proof-photo', 'proof-signature' => $this->proof($request, $id, $kind),
            'maintenance-invoice' => [MaintenanceRecord::findOrFail($id)->invoice_path, $user->can('maintenance.view')],
            'fuel-receipt' => [($f = FuelRecord::findOrFail($id))->receipt_path, $user->can('fuel.view') || $f->created_by === $user->id],
            'expense-receipt' => [($e = Expense::findOrFail($id))->receipt_path, $user->can('expenses.view') || $e->created_by === $user->id],
            default => abort(404),
        };
        abort_unless($allowed, 403);

        return Uploads::download($path, $companyId);
    }

    private function proof(Request $request, int $id, string $kind): array
    {
        $proof = DeliveryProof::findOrFail($id);
        $delivery = Delivery::findOrFail($proof->delivery_id); // tenant-scoped

        return [$kind === 'proof-photo' ? $proof->file_path : $proof->recipient_signature_path, $request->user()->can('view', $delivery)];
    }
}
