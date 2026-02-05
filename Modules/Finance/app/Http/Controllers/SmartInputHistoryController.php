<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\{RedirectResponse, Request};
use Inertia\{Inertia, Response};
use Modules\Finance\Models\SmartInputHistory;

class SmartInputHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = SmartInputHistory::forUser(auth()->id())
            ->with('transaction:id,description,amount,transaction_type')
            ->latest();

        if ($request->filled('input_type')) {
            $query->where('input_type', $request->input('input_type'));
        }

        if ($request->filled('saved')) {
            $query->where('transaction_saved', $request->input('saved') === 'yes');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return Inertia::render('Finance::smart-input-history/index', [
            'histories' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['input_type', 'saved', 'date_from', 'date_to']),
        ]);
    }

    public function destroy(SmartInputHistory $history): RedirectResponse
    {
        if ($history->user_id !== auth()->id()) {
            abort(403);
        }

        $history->delete();

        return redirect()
            ->route('dashboard.finance.smart-input-history.index')
            ->with('success', 'History entry deleted.');
    }
}
