<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\StoreSupplierRequest;
use App\Http\Requests\Pharmacy\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PharmacySupplierController extends Controller
{
    use RespondsWithPage;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $query = Supplier::query()
            ->when($request->filled('q'), function ($builder) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $builder->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)->orWhere('contact', 'like', $term);
                });
            })
            ->latest('id');

        return $this->paged($request, $query, fn (Supplier $supplier) => [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact' => $supplier->contact,
            'address' => $supplier->address,
        ]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()?->tenant_id;
        $supplier = Supplier::query()->create($data);
        activity_log('created', $supplier, $data, 'Supplier '.$supplier->name.' dibuat.', 'pharmacy');

        return response()->json(['data' => $supplier, 'message' => 'Supplier disimpan.']);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return response()->json(['data' => $supplier]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());
        activity_log('updated', $supplier, $request->validated(), 'Supplier '.$supplier->name.' diubah.', 'pharmacy');

        return response()->json(['data' => $supplier->fresh(), 'message' => 'Supplier diperbarui.']);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchaseOrders()->exists()) {
            throw ValidationException::withMessages([
                'supplier' => ['Supplier tidak dapat dihapus karena sudah dipakai pada purchase order.'],
            ]);
        }

        $name = $supplier->name;
        $supplier->delete();
        activity_log('deleted', $supplier, [], 'Supplier '.$name.' dihapus.', 'pharmacy');

        return response()->json(['message' => 'Supplier dihapus.']);
    }
}
