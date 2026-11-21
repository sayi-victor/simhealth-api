<?php

namespace App\Http\Resources;

use App\Models\PatientInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PatientInvoice */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'patient_id' => $this->patient_id,
            'encounter_id' => $this->encounter_id,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_due' => $this->balance_due,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_date' => $this->due_date?->toDateString(),
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => $this->patient ? [
                'id' => $this->patient->id,
                'mrn' => $this->patient->mrn,
                'full_name' => $this->patient->full_name,
            ] : null),
            'encounter' => $this->whenLoaded('encounter', fn () => $this->encounter ? [
                'id' => $this->encounter->id,
                'encounter_number' => $this->encounter->encounter_number,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'category' => $i->category,
                'code' => $i->code,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'discount_amount' => $i->discount_amount,
                'line_total' => $i->line_total,
            ])->values()->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'payer_type' => $p->payer_type,
                'payment_method' => $p->payment_method,
                'amount' => $p->amount,
                'reference_number' => $p->reference_number,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'received_by' => $p->receiver?->full_name,
                'notes' => $p->notes,
            ])->values()->all()),
            'claims' => $this->whenLoaded('claims', fn () => ClaimResource::collection($this->claims)),
        ];
    }
}
