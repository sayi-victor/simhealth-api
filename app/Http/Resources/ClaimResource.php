<?php

namespace App\Http\Resources;

use App\Models\InsuranceClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InsuranceClaim */
class ClaimResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'claim_number' => $this->claim_number,
            'invoice_id' => $this->invoice_id,
            'patient_id' => $this->patient_id,
            'payer_name' => $this->payer_name,
            'policy_number' => $this->policy_number,
            'status' => $this->status,
            'claimed_amount' => $this->claimed_amount,
            'approved_amount' => $this->approved_amount,
            'paid_amount' => $this->paid_amount,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'adjudicated_at' => $this->adjudicated_at?->toIso8601String(),
            'denial_reason' => $this->denial_reason,
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
            ] : null),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'total_amount' => $this->invoice->total_amount,
                'balance_due' => $this->invoice->balance_due,
                'status' => $this->invoice->status,
            ] : null),
        ];
    }
}
