<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Models\Branch;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class RoomController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Room::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Ruangan'],
            ['data' => 'branch', 'name' => 'branch.name', 'title' => 'Cabang'],
            ['data' => 'type', 'name' => 'type', 'title' => 'Tipe'],
            ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('management.rooms.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Room::class);

        return DataTables::eloquent(Room::query()->with('branch'))
            ->addIndexColumn()
            ->addColumn('branch', fn (Room $room) => e($room->branch?->name ?: '-'))
            ->editColumn('type', fn (Room $room) => e($room->type->label()))
            ->editColumn('is_active', function (Room $room) {
                return $room->is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>';
            })
            ->editColumn('created_at', fn (Room $room) => $room->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Room $room) => view('management.rooms.partials.actions', compact('room'))->render())
            ->rawColumns(['is_active', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Room::class);

        return view('management.rooms.create', $this->formData());
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $room = Room::query()->create($request->validated());

        activity_log('created', $room, $room->toArray(), 'Created room '.$room->name, 'rooms');

        return redirect()->route('management.rooms.index')->with('success', 'Ruangan created successfully.');
    }

    public function show(Room $room): View
    {
        $this->authorize('view', $room);
        $room->load('branch');

        return view('management.rooms.show', compact('room'));
    }

    public function edit(Room $room): View
    {
        $this->authorize('update', $room);

        return view('management.rooms.edit', $this->formData($room));
    }

    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        $data = $request->validated();
        $room->update($data);

        activity_log('updated', $room, $data, 'Updated room '.$room->name, 'rooms');

        return redirect()->route('management.rooms.index')->with('success', 'Ruangan updated successfully.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);

        activity_log('deleted', $room, ['name' => $room->name], 'Deleted room '.$room->name, 'rooms');
        $room->delete();

        return redirect()->route('management.rooms.index')->with('success', 'Ruangan deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Room $room = null): array
    {
        return [
            'room' => $room,
            'branches' => Branch::query()->orderBy('name')->get(),
        ];
    }
}
