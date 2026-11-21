<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\InvoicePayment;
use App\Models\PatientInsurance;
use App\Models\PatientInvoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PatientInvoice>
     */
    public function listInvoices(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = PatientInvoice::query()->with([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'encounter:id,encounter_number',
            'items',
            'payments.receiver:id,title,first_name,middle_name,last_name,suffix',
            'claims',
        ]);

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('invoice_number', 'like', $like)
                    ->orWhereHas('patient', function ($pq) use ($like) {
                        $pq->where('mrn', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function createInvoice(array $data, User $actor, Request $request): PatientInvoice
    {
        return DB::transaction(function () use ($data, $actor, $request) {
            $items = $data['items'];
            $invoiceDiscount = (float) ($data['discount_amount'] ?? 0);
            unset($data['items']);

            $subtotal = 0.0;
            $preparedItems = [];

            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 1);
                $unitPrice = (float) $item['unit_price'];
                $lineDiscount = (float) ($item['discount_amount'] ?? 0);
                $lineTotal = max(0, round(($qty * $unitPrice) - $lineDiscount, 2));
                $subtotal += $lineTotal;

                $preparedItems[] = [
                    'category' => $item['category'] ?? 'service',
                    'code' => $item['code'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $qty,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'discount_amount' => number_format($lineDiscount, 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                    'provider_id' => $item['provider_id'] ?? null,
                ];
            }

            $total = max(0, round($subtotal - $invoiceDiscount, 2));

            $invoice = PatientInvoice::create([
                'invoice_number' => 'TMP-'.Str::uuid()->toString(),
                'patient_id' => $data['patient_id'],
                'encounter_id' => $data['encounter_id'] ?? null,
                'facility_id' => $data['facility_id'] ?? $actor->facility_id,
                'patient_insurance_id' => $data['patient_insurance_id'] ?? null,
                'status' => $total <= 0 ? 'paid' : 'issued',
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discount_amount' => number_format($invoiceDiscount, 2, '.', ''),
                'total_amount' => number_format($total, 2, '.', ''),
                'paid_amount' => '0.00',
                'balance_due' => number_format($total, 2, '.', ''),
                'issued_at' => now(),
                'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $invoice->forceFill([
                'invoice_number' => sprintf('INV-%06d', $invoice->id),
            ])->save();

            foreach ($preparedItems as $line) {
                $invoice->items()->create($line);
            }

            $this->audit->log(
                event: 'billing.invoice_created',
                category: 'billing',
                user: $actor,
                auditable: $invoice,
                success: true,
                metadata: ['invoice_id' => $invoice->id, 'total_amount' => $invoice->total_amount],
                request: $request,
            );

            return $this->loadInvoice($invoice);
        });
    }

    /** @param array<string, mixed> $data */
    public function recordPayment(PatientInvoice $invoice, array $data, User $actor, Request $request): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data, $actor, $request) {
            $inv = PatientInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($inv->status === 'void') {
                throw ValidationException::withMessages([
                    'invoice' => ['Cannot record payment on a voided invoice.'],
                ]);
            }

            $amount = round((float) $data['amount'], 2);
            $balance = round((float) $inv->balance_due, 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment amount must be greater than zero.'],
                ]);
            }

            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => ["Payment ({$amount}) exceeds remaining balance ({$balance})."],
                ]);
            }

            $payment = InvoicePayment::create([
                'receipt_number' => 'TMP-'.Str::uuid()->toString(),
                'invoice_id' => $inv->id,
                'patient_id' => $inv->patient_id,
                'payer_type' => $data['payer_type'] ?? 'patient',
                'payment_method' => $data['payment_method'] ?? 'cash',
                'amount' => number_format($amount, 2, '.', ''),
                'reference_number' => $data['reference_number'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'received_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $payment->forceFill([
                'receipt_number' => sprintf('RCP-%06d', $payment->id),
            ])->save();

            $newPaid = round((float) $inv->paid_amount + $amount, 2);
            $newBalance = max(0, round((float) $inv->total_amount - $newPaid, 2));

            $inv->forceFill([
                'paid_amount' => number_format($newPaid, 2, '.', ''),
                'balance_due' => number_format($newBalance, 2, '.', ''),
                'status' => $newBalance <= 0 ? 'paid' : 'partially_paid',
            ])->save();

            $this->audit->log(
                event: 'billing.payment_recorded',
                category: 'billing',
                user: $actor,
                auditable: $payment,
                success: true,
                metadata: [
                    'invoice_id' => $inv->id,
                    'amount' => $payment->amount,
                    'method' => $payment->payment_method,
                ],
                request: $request,
            );

            return $payment->load('receiver:id,title,first_name,middle_name,last_name,suffix');
        });
    }

    public function voidInvoice(PatientInvoice $invoice, User $actor, Request $request): PatientInvoice
    {
        if ((float) $invoice->paid_amount > 0) {
            throw ValidationException::withMessages([
                'status' => ['Invoices with recorded payments cannot be voided.'],
            ]);
        }

        $invoice->forceFill([
            'status' => 'void',
            'balance_due' => '0.00',
        ])->save();

        $this->audit->log(
            event: 'billing.invoice_voided',
            category: 'billing',
            user: $actor,
            auditable: $invoice,
            success: true,
            metadata: ['invoice_id' => $invoice->id],
            request: $request,
        );

        return $this->loadInvoice($invoice);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, InsuranceClaim>
     */
    public function listClaims(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $query = InsuranceClaim::query()->with([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'invoice:id,invoice_number,total_amount,balance_due,status',
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['patient_id'])) {
            $query->where('patient_id', (int) $filters['patient_id']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function createClaim(array $data, User $actor, Request $request): InsuranceClaim
    {
        return DB::transaction(function () use ($data, $actor, $request) {
            $invoice = PatientInvoice::findOrFail($data['invoice_id']);
            $insurance = ! empty($data['patient_insurance_id'])
                ? PatientInsurance::find($data['patient_insurance_id'])
                : ($invoice->patient_insurance_id ? PatientInsurance::find($invoice->patient_insurance_id) : null);

            $claimedAmount = isset($data['claimed_amount'])
                ? round((float) $data['claimed_amount'], 2)
                : round((float) $invoice->balance_due, 2);

            $claim = InsuranceClaim::create([
                'claim_number' => 'TMP-'.Str::uuid()->toString(),
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'patient_insurance_id' => $insurance?->id,
                'payer_name' => $data['payer_name'] ?? $insurance?->provider_name ?? 'Insurance Payer',
                'policy_number' => $data['policy_number'] ?? $insurance?->policy_number,
                'status' => ($data['submit_now'] ?? true) ? 'submitted' : 'draft',
                'claimed_amount' => number_format($claimedAmount, 2, '.', ''),
                'submitted_at' => ($data['submit_now'] ?? true) ? now() : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $claim->forceFill([
                'claim_number' => sprintf('CLM-%06d', $claim->id),
            ])->save();

            $this->audit->log(
                event: 'claims.created',
                category: 'billing',
                user: $actor,
                auditable: $claim,
                success: true,
                metadata: ['claim_id' => $claim->id, 'invoice_id' => $invoice->id],
                request: $request,
            );

            return $claim->load(['patient:id,mrn,title,first_name,middle_name,last_name', 'invoice:id,invoice_number,total_amount,balance_due,status']);
        });
    }

    /** @param array<string, mixed> $data */
    public function adjudicateClaim(InsuranceClaim $claim, array $data, User $actor, Request $request): InsuranceClaim
    {
        return DB::transaction(function () use ($claim, $data, $actor, $request) {
            $status = $data['status'];
            $approved = isset($data['approved_amount']) ? round((float) $data['approved_amount'], 2) : null;
            $paid = isset($data['paid_amount']) ? round((float) $data['paid_amount'], 2) : (float) $claim->paid_amount;

            $claim->forceFill([
                'status' => $status,
                'approved_amount' => $approved !== null ? number_format($approved, 2, '.', '') : $claim->approved_amount,
                'paid_amount' => number_format($paid, 2, '.', ''),
                'adjudicated_at' => now(),
                'denial_reason' => $data['denial_reason'] ?? $claim->denial_reason,
                'notes' => $data['notes'] ?? $claim->notes,
            ])->save();

            // If claim remittance is paid and post_payment=true, apply insurance payment to invoice automatically
            if (! empty($data['post_payment']) && $paid > 0) {
                $invoice = PatientInvoice::findOrFail($claim->invoice_id);
                $applyAmount = min($paid, (float) $invoice->balance_due);
                if ($applyAmount > 0) {
                    $this->recordPayment($invoice, [
                        'payer_type' => 'insurance',
                        'payment_method' => 'insurance_remittance',
                        'amount' => $applyAmount,
                        'reference_number' => $claim->claim_number,
                        'notes' => "Insurance remittance for {$claim->claim_number}",
                    ], $actor, $request);
                }
            }

            $this->audit->log(
                event: 'claims.adjudicated',
                category: 'billing',
                user: $actor,
                auditable: $claim,
                success: true,
                metadata: ['claim_id' => $claim->id, 'status' => $status],
                request: $request,
            );

            return $claim->fresh(['patient:id,mrn,title,first_name,middle_name,last_name', 'invoice:id,invoice_number,total_amount,balance_due,status']);
        });
    }

    public function loadInvoice(PatientInvoice $invoice): PatientInvoice
    {
        return $invoice->fresh([
            'patient:id,mrn,title,first_name,middle_name,last_name',
            'encounter:id,encounter_number',
            'items',
            'payments.receiver:id,title,first_name,middle_name,last_name,suffix',
            'claims',
        ]);
    }
}
