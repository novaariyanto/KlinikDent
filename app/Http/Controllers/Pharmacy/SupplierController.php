<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreSupplierRequest;
use App\Http\Requests\Pharmacy\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

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

        return view('pharmacy.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('pharmacy.suppliers.form', ['supplier' => null]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()?->tenant_id;

        $supplier = Supplier::query()->create($data);

        activity_log('created', $supplier, $data, 'Supplier '.$supplier->name.' dibuat.', 'pharmacy');

        return redirect()->route('pharmacy.purchases.suppliers')->with('success', 'Supplier disimpan.');
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('pharmacy.suppliers.form', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        activity_log('updated', $supplier, $request->validated(), 'Supplier '.$supplier->name.' diubah.', 'pharmacy');

        return redirect()->route('pharmacy.purchases.suppliers')->with('success', 'Supplier diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'Supplier tidak dapat dihapus karena sudah dipakai pada purchase order.');
        }

        $name = $supplier->name;
        $supplier->delete();

        activity_log('deleted', $supplier, [], 'Supplier '.$name.' dihapus.', 'pharmacy');

        return redirect()->route('pharmacy.purchases.suppliers')->with('success', 'Supplier dihapus.');
    }
}
