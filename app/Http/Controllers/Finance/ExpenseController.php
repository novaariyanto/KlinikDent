<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Support\Finance\FinanceException;
use App\Support\Finance\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    use ScopesFinanceBranch;

    public function __construct(protected FinanceService $finance)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::query()->with(['category', 'supplier', 'cashAccount', 'branch']);
        $this->constrainBranch($query, $request->user());

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date('date_to'));
        }

        $expenses = $query->latest('expense_date')->latest('id')->paginate(20)->withQueryString();
        $accounts = CashAccount::query();
        $this->constrainBranch($accounts, $request->user());
        $accounts = $accounts->orderBy('name')->get();

        return view('finance.expenses.index', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::query()->orderBy('name')->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'accounts' => $accounts,
            'branches' => $this->assignedBranches($request->user()),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        try {
            $this->finance->recordExpense($request->validated(), $request->user());
        } catch (FinanceException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('finance.expenses')->with('success', 'Pengeluaran tercatat.');
    }

    public function suppliers(Request $request): View
    {
        abort_unless($request->user()?->can('supplier.view'), 403);

        $suppliers = Supplier::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('contact', 'like', $term);
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('finance.expenses.suppliers', compact('suppliers'));
    }
}
