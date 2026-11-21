<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Bed;
use App\Models\ClinicalOrder;
use App\Models\ClinicalOrderResult;
use App\Models\Drug;
use App\Models\DrugLot;
use App\Models\Encounter;
use App\Models\InsuranceClaim;
use App\Models\InventoryItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\PatientInvoice;
use App\Models\PrescriptionDispensation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** @return array<string, mixed> */
    public function overview(?string $dateFrom, ?string $dateTo): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : now()->startOfMonth();
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();

        $totalBeds = Bed::where('is_active', true)->count();
        $occupiedBeds = Bed::where('is_active', true)->where('status', 'occupied')->count();
        $occupancyPct = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0.0;

        $encountersByCategory = Encounter::query()
            ->whereBetween('encounter_date', [$from, $to])
            ->select('visit_category', DB::raw('COUNT(*) as total'))
            ->groupBy('visit_category')
            ->pluck('total', 'visit_category')
            ->all();

        $appointmentsByStatus = Appointment::query()
            ->whereBetween('starts_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $ordersByType = ClinicalOrder::query()
            ->whereBetween('ordered_at', [$from, $to])
            ->select('order_type', DB::raw('COUNT(*) as total'))
            ->groupBy('order_type')
            ->pluck('total', 'order_type')
            ->all();

        $abnormalResults = ClinicalOrderResult::query()
            ->whereBetween('resulted_at', [$from, $to])
            ->where('abnormal_flag', '!=', 'normal')
            ->count();

        $pharmacyDispensedCount = PrescriptionDispensation::query()
            ->whereBetween('dispensed_at', [$from, $to])
            ->count();

        $pharmacyRevenue = (float) PrescriptionDispensation::query()
            ->whereBetween('dispensed_at', [$from, $to])
            ->sum('total_price');

        return [
            'period' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'patients' => [
                'total_active' => Patient::count(),
                'new_in_period' => Patient::whereBetween('created_at', [$from, $to])->count(),
            ],
            'encounters' => [
                'total_in_period' => array_sum($encountersByCategory),
                'by_category' => $encountersByCategory,
            ],
            'appointments' => [
                'total_in_period' => array_sum($appointmentsByStatus),
                'by_status' => $appointmentsByStatus,
            ],
            'inpatient' => [
                'active_admissions' => Admission::where('status', 'admitted')->count(),
                'total_beds' => $totalBeds,
                'occupied_beds' => $occupiedBeds,
                'available_beds' => max(0, $totalBeds - $occupiedBeds),
                'occupancy_rate_pct' => $occupancyPct,
            ],
            'orders' => [
                'total_in_period' => array_sum($ordersByType),
                'by_type' => $ordersByType,
                'abnormal_results' => $abnormalResults,
            ],
            'pharmacy' => [
                'dispensations_count' => $pharmacyDispensedCount,
                'dispensed_revenue' => number_format($pharmacyRevenue, 2, '.', ''),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function revenue(?string $dateFrom, ?string $dateTo): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : now()->startOfMonth();
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();

        $invoiceTotals = PatientInvoice::query()
            ->where('status', '!=', 'void')
            ->whereBetween('issued_at', [$from, $to])
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(total_amount), 0) as total_billed, COALESCE(SUM(paid_amount), 0) as total_paid, COALESCE(SUM(balance_due), 0) as total_outstanding')
            ->first();

        $paymentsByMethod = InvoicePayment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'count' => (int) $row->count,
                'total' => number_format((float) $row->total, 2, '.', ''),
            ])
            ->values()
            ->all();

        $claimsByStatus = InsuranceClaim::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(claimed_amount), 0) as claimed, COALESCE(SUM(paid_amount), 0) as paid')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
                'claimed_amount' => number_format((float) $row->claimed, 2, '.', ''),
                'paid_amount' => number_format((float) $row->paid, 2, '.', ''),
            ])
            ->values()
            ->all();

        return [
            'period' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'invoices' => [
                'count' => (int) ($invoiceTotals->invoice_count ?? 0),
                'total_billed' => number_format((float) ($invoiceTotals->total_billed ?? 0), 2, '.', ''),
                'total_paid' => number_format((float) ($invoiceTotals->total_paid ?? 0), 2, '.', ''),
                'total_outstanding' => number_format((float) ($invoiceTotals->total_outstanding ?? 0), 2, '.', ''),
            ],
            'payments_by_method' => $paymentsByMethod,
            'claims_by_status' => $claimsByStatus,
        ];
    }

    /** @return array<string, mixed> */
    public function inventoryAlerts(): array
    {
        $lowStockItems = InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->orderBy('quantity_on_hand')
            ->get(['id', 'sku', 'name', 'category', 'quantity_on_hand', 'reorder_level', 'unit']);

        $expiringLots = DrugLot::query()
            ->with('drug:id,code,name')
            ->where('is_active', true)
            ->where('quantity_on_hand', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(60)->toDateString())
            ->orderBy('expires_at')
            ->get()
            ->map(fn (DrugLot $lot) => [
                'id' => $lot->id,
                'drug_code' => $lot->drug?->code,
                'drug_name' => $lot->drug?->name,
                'lot_number' => $lot->lot_number,
                'quantity_on_hand' => $lot->quantity_on_hand,
                'expires_at' => $lot->expires_at?->toDateString(),
            ])
            ->values()
            ->all();

        $lowStockDrugs = Drug::query()
            ->withSum(['lots as total_on_hand' => fn ($q) => $q->where('is_active', true)], 'quantity_on_hand')
            ->where('is_active', true)
            ->get()
            ->filter(fn (Drug $d) => ((int) ($d->total_on_hand ?? 0)) <= $d->reorder_level)
            ->map(fn (Drug $d) => [
                'id' => $d->id,
                'code' => $d->code,
                'name' => $d->name,
                'total_on_hand' => (int) ($d->total_on_hand ?? 0),
                'reorder_level' => $d->reorder_level,
            ])
            ->values()
            ->all();

        return [
            'low_stock_inventory' => $lowStockItems,
            'low_stock_drugs' => $lowStockDrugs,
            'expiring_drug_lots' => $expiringLots,
        ];
    }
}
