<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Models\CashAccount;
use App\Models\CashMutation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashBankController extends Controller
{
    use ScopesFinanceBranch;

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('cash_bank.view'), 403);

        $accounts = CashAccount::query()->with('branch');
        $this->constrainBranch($accounts, $request->user());
        $accounts = $accounts->orderBy('name')->get();

        $accountId = $request->integer('cash_account_id') ?: $accounts->first()?->id;

        $mutations = collect();
        $selected = null;

        if ($accountId) {
            $lookup = CashAccount::query();
            $this->constrainBranch($lookup, $request->user());
            $selected = $accounts->firstWhere('id', $accountId) ?? $lookup->find($accountId);

            if ($selected) {
                $this->authorize('view', $selected);
                $mutations = CashMutation::query()
                    ->where('cash_account_id', $selected->id)
                    ->latest('mutated_at')
                    ->latest('id')
                    ->paginate(20)
                    ->withQueryString();
            }
        }

        return view('finance.cash-bank', [
            'accounts' => $accounts,
            'selected' => $selected,
            'mutations' => $mutations,
            'totalBalance' => $accounts->reduce(
                fn (string $carry, CashAccount $account) => bcadd($carry, (string) $account->balance, 2),
                '0.00'
            ),
        ]);
    }
}
