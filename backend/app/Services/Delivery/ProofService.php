<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\ProofType;
use App\Exceptions\DomainException;
use App\Models\Delivery;
use App\Models\DeliveryProof;
use App\Models\Driver;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ProofService
{
    public function add(Delivery $delivery, ?Driver $driver, array $data, ?UploadedFile $photo): DeliveryProof
    {
        if (! in_array($delivery->status, [DeliveryStatus::InTransit, DeliveryStatus::Arrived, DeliveryStatus::PickedUp], true)) {
            throw new DomainException('Proof can only be added while the delivery is underway.', 'proof_not_allowed');
        }
        $type = ProofType::from($data['proof_type']);

        $proof = new DeliveryProof([
            'driver_id' => $driver?->id,
            'proof_type' => $type,
            'recipient_name' => $data['recipient_name'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        switch ($type) {
            case ProofType::Photo:
                if (! $photo) {
                    throw new DomainException('A photo is required.', 'photo_required');
                }
                $proof->file_path = Uploads::store($photo, $delivery->company_id, 'proofs');
                break;
            case ProofType::Signature:
                try {
                    $proof->recipient_signature_path = Uploads::storeDataUrl((string) ($data['signature'] ?? ''), $delivery->company_id, 'signatures');
                } catch (\InvalidArgumentException) {
                    throw new DomainException('The signature image is invalid.', 'signature_invalid');
                }
                if (blank($proof->recipient_name)) {
                    throw new DomainException('Enter the name of the person who signed.', 'recipient_required');
                }
                break;
            case ProofType::Otp:
                $key = "otp:{$delivery->id}";
                if (RateLimiter::tooManyAttempts($key, 5)) {
                    throw new DomainException('Too many incorrect codes. Try again in a few minutes.', 'otp_locked', 429);
                }
                if (! $delivery->otp_hash || ! Hash::check((string) ($data['otp'] ?? ''), $delivery->otp_hash)) {
                    RateLimiter::hit($key, 600);
                    throw new DomainException('The delivery code is incorrect.', 'otp_invalid');
                }
                RateLimiter::clear($key);
                break;
            case ProofType::Confirmation:
                break;
        }

        $delivery->proofs()->save($proof);

        return $proof;
    }
}
