<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreInvoiceRequest;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Http\Resources\ClaimResource;
use App\Http\Resources\InvoiceResource;
use App\Models\InsuranceClaim;
use App\Models\PatientInvoice;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    public function indexInvoices(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            $this->billing->listInvoices($request->only(['patient_id', 'status', 'search', 'per_page']))
        );
    }

    public function storeInvoice(StoreInvoiceRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $invoice = $this->billing->createInvoice($request->validated(), $actor, $request);

        return (new InvoiceResource($invoice))->response()->setStatusCode(201);
    }

    public function showInvoice(PatientInvoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->billing->loadInvoice($invoice));
    }

    public function storePayment(StorePaymentRequest $request, PatientInvoice $invoice): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $payment = $this->billing->recordPayment($invoice, $request->validated(), $actor, $request);

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'invoice_id' => $payment->invoice_id,
                'payer_type' => $payment->payer_type,
                'payment_method' => $payment->payment_method,
                'amount' => $payment->amount,
                'reference_number' => $payment->reference_number,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'received_by' => $payment->receiver?->full_name,
            ],
        ], 201);
    }

    public function voidInvoice(Request $request, PatientInvoice $invoice): InvoiceResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new InvoiceResource($this->billing->voidInvoice($invoice, $actor, $request));
    }

    public function indexClaims(Request $request): AnonymousResourceCollection
    {
        return ClaimResource::collection(
            $this->billing->listClaims($request->only(['status', 'patient_id', 'per_page']))
        );
    }

    public function storeClaim(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:patient_invoices,id'],
            'patient_insurance_id' => ['nullable', 'integer', 'exists:patient_insurances,id'],
            'payer_name' => ['nullable', 'string', 'max:200'],
            'policy_number' => ['nullable', 'string', 'max:100'],
            'claimed_amount' => ['nullable', 'numeric', 'gt:0'],
            'submit_now' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $claim = $this->billing->createClaim($validated, $actor, $request);

        return (new ClaimResource($claim))->response()->setStatusCode(201);
    }

    public function adjudicateClaim(Request $request, InsuranceClaim $claim): ClaimResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'partially_approved', 'denied', 'paid'])],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'post_payment' => ['sometimes', 'boolean'],
            'denial_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        return new ClaimResource($this->billing->adjudicateClaim($claim, $validated, $actor, $request));
    }
}
