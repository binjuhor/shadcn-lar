<?php

namespace Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Invoice\Models\Invoice;

class InvoiceReportController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = auth()->id();

        $range = $request->get('range', '6m');
        $startDate = $request->get('start');
        $endDate = $request->get('end');

        [$dateFrom, $dateTo, $groupBy] = $this->parseDateRange($range, $startDate, $endDate);

        $incomeTrend = $this->getIncomeTrend($userId, $dateFrom, $dateTo, $groupBy);
        $statusBreakdown = $this->getStatusBreakdown($userId, $dateFrom, $dateTo);
        $clientBreakdown = $this->getClientBreakdown($userId, $dateFrom, $dateTo);
        $summary = $this->getSummary($userId, $dateFrom, $dateTo);

        return Inertia::render('Invoice::reports/index', [
            'filters' => [
                'range' => $range,
                'startDate' => $dateFrom->format('Y-m-d'),
                'endDate' => $dateTo->format('Y-m-d'),
            ],
            'incomeTrend' => $incomeTrend,
            'statusBreakdown' => $statusBreakdown,
            'clientBreakdown' => $clientBreakdown,
            'summary' => $summary,
        ]);
    }

    protected function parseDateRange(string $range, ?string $start, ?string $end): array
    {
        $now = Carbon::now();

        switch ($range) {
            case '30d':
                $dateFrom = $now->copy()->subDays(30);
                $dateTo = $now->copy();
                $groupBy = 'day';
                break;
            case '6m':
                $dateFrom = $now->copy()->subMonths(6)->startOfMonth();
                $dateTo = $now->copy()->endOfMonth();
                $groupBy = 'month';
                break;
            case '12m':
                $dateFrom = $now->copy()->subMonths(12)->startOfMonth();
                $dateTo = $now->copy()->endOfMonth();
                $groupBy = 'month';
                break;
            case 'ytd':
                $dateFrom = $now->copy()->startOfYear();
                $dateTo = $now->copy();
                $groupBy = 'month';
                break;
            case 'custom':
                $dateFrom = $start ? Carbon::parse($start) : $now->copy()->subMonths(6);
                $dateTo = $end ? Carbon::parse($end) : $now->copy();
                $daysDiff = $dateFrom->diffInDays($dateTo);
                $groupBy = $daysDiff <= 60 ? 'day' : 'month';
                break;
            default:
                $dateFrom = $now->copy()->subMonths(6)->startOfMonth();
                $dateTo = $now->copy()->endOfMonth();
                $groupBy = 'month';
        }

        return [$dateFrom, $dateTo, $groupBy];
    }

    protected function getIncomeTrend(int $userId, Carbon $dateFrom, Carbon $dateTo, string $groupBy): array
    {
        $dateFormat = $groupBy === 'day' ? '%Y-%m-%d' : '%Y-%m';
        $phpFormat = $groupBy === 'day' ? 'Y-m-d' : 'Y-m';

        $invoices = Invoice::select(
            DB::raw("DATE_FORMAT(invoice_date, '{$dateFormat}') as period"),
            'status',
            DB::raw('SUM(total) as total'),
            DB::raw('COUNT(*) as count')
        )
            ->where('user_id', $userId)
            ->whereBetween('invoice_date', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->groupBy('period', 'status')
            ->get();

        $periods = [];
        $current = $dateFrom->copy();

        while ($current <= $dateTo) {
            $periodKey = $current->format($phpFormat);
            $periods[$periodKey] = [
                'period' => $periodKey,
                'total' => 0,
                'paid' => 0,
                'pending' => 0,
                'count' => 0,
            ];
            $current = $groupBy === 'day' ? $current->addDay() : $current->addMonth();
        }

        foreach ($invoices as $invoice) {
            if (! isset($periods[$invoice->period])) {
                continue;
            }

            $periods[$invoice->period]['total'] += (float) $invoice->total;
            $periods[$invoice->period]['count'] += (int) $invoice->count;

            if ($invoice->status === 'paid') {
                $periods[$invoice->period]['paid'] += (float) $invoice->total;
            } else {
                $periods[$invoice->period]['pending'] += (float) $invoice->total;
            }
        }

        return array_values($periods);
    }

    protected function getStatusBreakdown(int $userId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $invoices = Invoice::select(
            'status',
            DB::raw('SUM(total) as total'),
            DB::raw('COUNT(*) as count')
        )
            ->where('user_id', $userId)
            ->whereBetween('invoice_date', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->groupBy('status')
            ->get();

        $statusColors = [
            'draft' => '#6b7280',
            'sent' => '#3b82f6',
            'paid' => '#10b981',
            'overdue' => '#ef4444',
            'cancelled' => '#9ca3af',
        ];

        $statusLabels = [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
        ];

        $breakdown = [];
        $grandTotal = $invoices->sum('total');

        foreach ($invoices as $invoice) {
            $breakdown[] = [
                'status' => $invoice->status,
                'label' => $statusLabels[$invoice->status] ?? ucfirst($invoice->status),
                'color' => $statusColors[$invoice->status] ?? '#6b7280',
                'amount' => (float) $invoice->total,
                'count' => (int) $invoice->count,
                'percentage' => $grandTotal > 0 ? round(((float) $invoice->total / $grandTotal) * 100, 1) : 0,
            ];
        }

        usort($breakdown, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $breakdown;
    }

    protected function getClientBreakdown(int $userId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $invoices = Invoice::select(
            'to_name',
            DB::raw('SUM(total) as total'),
            DB::raw('COUNT(*) as count')
        )
            ->where('user_id', $userId)
            ->whereBetween('invoice_date', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->groupBy('to_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $colors = [
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
        ];

        $breakdown = [];
        $grandTotal = $invoices->sum('total');

        foreach ($invoices as $index => $invoice) {
            $breakdown[] = [
                'name' => $invoice->to_name,
                'color' => $colors[$index % count($colors)],
                'amount' => (float) $invoice->total,
                'count' => (int) $invoice->count,
                'percentage' => $grandTotal > 0 ? round(((float) $invoice->total / $grandTotal) * 100, 1) : 0,
            ];
        }

        return $breakdown;
    }

    protected function getSummary(int $userId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $currentPeriod = Invoice::where('user_id', $userId)
            ->whereBetween('invoice_date', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->selectRaw("
                SUM(total) as total,
                SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid,
                SUM(CASE WHEN status != 'paid' AND status != 'cancelled' THEN total ELSE 0 END) as pending,
                COUNT(*) as count
            ")
            ->first();

        $previousFrom = $dateFrom->copy()->subDays($dateFrom->diffInDays($dateTo));
        $previousTo = $dateFrom->copy()->subDay();

        $previousPeriod = Invoice::where('user_id', $userId)
            ->whereBetween('invoice_date', [$previousFrom->startOfDay(), $previousTo->endOfDay()])
            ->selectRaw("SUM(total) as total")
            ->first();

        $currentTotal = (float) ($currentPeriod->total ?? 0);
        $previousTotal = (float) ($previousPeriod->total ?? 0);
        $percentageChange = $previousTotal > 0 ? round((($currentTotal - $previousTotal) / $previousTotal) * 100, 1) : 0;

        return [
            'totalInvoiced' => $currentTotal,
            'totalPaid' => (float) ($currentPeriod->paid ?? 0),
            'totalPending' => (float) ($currentPeriod->pending ?? 0),
            'invoiceCount' => (int) ($currentPeriod->count ?? 0),
            'previousPeriodChange' => $percentageChange,
        ];
    }
}
