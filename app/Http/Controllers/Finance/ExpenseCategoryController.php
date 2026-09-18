<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseCategoryRequest;
use App\Http\Requests\Finance\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('name')
            ->paginate(20);

        return view('finance.expenses.categories', compact('categories'));
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $category = ExpenseCategory::query()->create([
            'tenant_id' => $request->user()?->tenant_id,
            'name' => $request->validated('name'),
        ]);

        activity_log('created', $category, $request->validated(), 'Kategori pengeluaran '.$category->name.' dibuat.', 'finance');

        return redirect()->route('finance.expenses.categories')->with('success', 'Kategori disimpan.');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update($request->validated());

        activity_log('updated', $expenseCategory, $request->validated(), 'Kategori pengeluaran '.$expenseCategory->name.' diubah.', 'finance');

        return redirect()->route('finance.expenses.categories')->with('success', 'Kategori diperbarui.');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('delete', $expenseCategory);

        if ($expenseCategory->expenses()->exists()) {
            return back()->with('error', 'Kategori tidak dapat dihapus karena sudah dipakai pada pengeluaran.');
        }

        $name = $expenseCategory->name;
        $expenseCategory->delete();

        activity_log('deleted', $expenseCategory, [], 'Kategori pengeluaran '.$name.' dihapus.', 'finance');

        return redirect()->route('finance.expenses.categories')->with('success', 'Kategori dihapus.');
    }
}
