<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DeliveryTrip;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AuditLogService;
use App\Support\ExcelExportStyler;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryTripPageController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $tripDate = trim((string) $request->string('trip_date', ''));
        $selectedTripDate = $tripDate !== '' ? $tripDate : null;

        $trips = DeliveryTrip::query()
            ->onlyListColumns()
            ->with([
                'members:id,delivery_trip_id,member_name',
                'creator:id,name',
            ])
            ->searchKeyword($search)
            ->when($selectedTripDate !== null, function ($query) use ($selectedTripDate): void {
                $query->whereDate('trip_date', $selectedTripDate);
            })
            ->orderByDesc('trip_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('delivery_trips.index', [
            'trips' => $trips,
            'search' => $search,
            'selectedTripDate' => $selectedTripDate,
        ]);
    }

    public function create(): View
    {
        return view('delivery_trips.create', [
            'users' => $this->teamMemberOptions(),
            'prefillDate' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $trip = DB::transaction(function () use ($data, $request): DeliveryTrip {
            $memberPayload = $this->resolveMembers(
                userIds: (array) ($data['member_user_ids'] ?? []),
                extraNamesRaw: (string) ($data['extra_member_names'] ?? '')
            );

            if ($memberPayload->isEmpty()) {
                throw ValidationException::withMessages([
                    'member_user_ids' => __('delivery_trip.members_required'),
                ]);
            }

            $tripDate = Carbon::parse((string) $data['trip_date']);
            $totalCost = $this->totalCostFromData($data);

            $trip = DeliveryTrip::query()->create([
                'trip_number' => $this->generateTripNumber($tripDate->toDateString()),
                'trip_date' => $tripDate->toDateString(),
                'driver_name' => trim((string) $data['driver_name']),
                'vehicle_plate' => $this->nullIfEmpty((string) ($data['vehicle_plate'] ?? '')),
                'member_count' => $memberPayload->count(),
                'fuel_cost' => (int) $data['fuel_cost'],
                'toll_cost' => (int) $data['toll_cost'],
                'meal_cost' => (int) $data['meal_cost'],
                'other_cost' => (int) $data['other_cost'],
                'total_cost' => $totalCost,
                'notes' => $this->nullIfEmpty((string) ($data['notes'] ?? '')),
                'created_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            ]);

            $trip->members()->createMany($memberPayload->all());

            $this->accountingService->postDeliveryTripExpense(
                tripId: (int) $trip->id,
                date: $tripDate,
                amount: $totalCost
            );

            $this->auditLogService->log(
                'delivery.trip.create',
                $trip,
                __('delivery_trip.audit_created', ['number' => $trip->trip_number]),
                $request,
                null,
                [
                    'trip_number' => $trip->trip_number,
                    'total_cost' => $trip->total_cost,
                    'member_count' => $trip->member_count,
                ]
            );

            return $trip;
        });

        return redirect()
            ->route('delivery-trips.show', $trip)
            ->with('success', __('delivery_trip.created_success', ['number' => $trip->trip_number]));
    }

    public function show(DeliveryTrip $deliveryTrip): View
    {
        $deliveryTrip->load([
            'members:id,delivery_trip_id,user_id,member_name',
            'members.user:id,name',
            'creator:id,name',
            'updater:id,name',
        ]);

        return view('delivery_trips.show', [
            'trip' => $deliveryTrip,
        ]);
    }

    public function edit(DeliveryTrip $deliveryTrip): View
    {
        $deliveryTrip->load('members:id,delivery_trip_id,user_id,member_name');

        $selectedUserIds = $deliveryTrip->members
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        $extraMemberNames = $deliveryTrip->members
            ->filter(fn ($member): bool => (int) ($member->user_id ?? 0) === 0)
            ->pluck('member_name')
            ->implode(PHP_EOL);

        return view('delivery_trips.edit', [
            'trip' => $deliveryTrip,
            'users' => $this->teamMemberOptions(),
            'selectedUserIds' => $selectedUserIds,
            'extraMemberNames' => $extraMemberNames,
        ]);
    }

    public function update(Request $request, DeliveryTrip $deliveryTrip): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data, $request, $deliveryTrip): void {
            $trip = DeliveryTrip::query()
                ->whereKey($deliveryTrip->id)
                ->lockForUpdate()
                ->firstOrFail();

            $memberPayload = $this->resolveMembers(
                userIds: (array) ($data['member_user_ids'] ?? []),
                extraNamesRaw: (string) ($data['extra_member_names'] ?? '')
            );

            if ($memberPayload->isEmpty()) {
                throw ValidationException::withMessages([
                    'member_user_ids' => __('delivery_trip.members_required'),
                ]);
            }

            $before = [
                'trip_date' => optional($trip->trip_date)->format('Y-m-d'),
                'driver_name' => $trip->driver_name,
                'vehicle_plate' => $trip->vehicle_plate,
                'member_count' => $trip->member_count,
                'fuel_cost' => (int) $trip->fuel_cost,
                'toll_cost' => (int) $trip->toll_cost,
                'meal_cost' => (int) $trip->meal_cost,
                'other_cost' => (int) $trip->other_cost,
                'total_cost' => (int) $trip->total_cost,
            ];
            $beforeTotalCost = (int) $trip->total_cost;

            $tripDate = Carbon::parse((string) $data['trip_date']);
            $totalCost = $this->totalCostFromData($data);

            $trip->update([
                'trip_date' => $tripDate->toDateString(),
                'driver_name' => trim((string) $data['driver_name']),
                'vehicle_plate' => $this->nullIfEmpty((string) ($data['vehicle_plate'] ?? '')),
                'member_count' => $memberPayload->count(),
                'fuel_cost' => (int) $data['fuel_cost'],
                'toll_cost' => (int) $data['toll_cost'],
                'meal_cost' => (int) $data['meal_cost'],
                'other_cost' => (int) $data['other_cost'],
                'total_cost' => $totalCost,
                'notes' => $this->nullIfEmpty((string) ($data['notes'] ?? '')),
                'updated_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            ]);

            $trip->members()->delete();
            $trip->members()->createMany($memberPayload->all());

            $this->accountingService->postDeliveryTripAdjustment(
                tripId: (int) $trip->id,
                date: $tripDate,
                difference: $totalCost - $beforeTotalCost
            );

            $this->auditLogService->log(
                'delivery.trip.update',
                $trip,
                __('delivery_trip.audit_updated', ['number' => $trip->trip_number]),
                $request,
                $before,
                [
                    'trip_date' => $trip->trip_date?->format('Y-m-d'),
                    'driver_name' => $trip->driver_name,
                    'vehicle_plate' => $trip->vehicle_plate,
                    'member_count' => $trip->member_count,
                    'fuel_cost' => (int) $trip->fuel_cost,
                    'toll_cost' => (int) $trip->toll_cost,
                    'meal_cost' => (int) $trip->meal_cost,
                    'other_cost' => (int) $trip->other_cost,
                    'total_cost' => (int) $trip->total_cost,
                ]
            );
        });

        return redirect()
            ->route('delivery-trips.show', $deliveryTrip)
            ->with('success', __('delivery_trip.updated_success'));
    }

    public function print(DeliveryTrip $deliveryTrip): View
    {
        $deliveryTrip->load([
            'members:id,delivery_trip_id,user_id,member_name',
            'members.user:id,name',
            'creator:id,name',
            'updater:id,name',
        ]);

        return view('delivery_trips.print', [
            'trip' => $deliveryTrip,
        ]);
    }

    public function exportPdf(DeliveryTrip $deliveryTrip)
    {
        $deliveryTrip->load([
            'members:id,delivery_trip_id,user_id,member_name',
            'members.user:id,name',
            'creator:id,name',
            'updater:id,name',
        ]);

        $filename = strtolower((string) $deliveryTrip->trip_number) . '.pdf';

        return Pdf::loadView('delivery_trips.print', [
            'trip' => $deliveryTrip,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait')->download($filename);
    }

    public function exportExcel(DeliveryTrip $deliveryTrip): StreamedResponse
    {
        $deliveryTrip->load([
            'members:id,delivery_trip_id,user_id,member_name',
            'members.user:id,name',
            'creator:id,name',
            'updater:id,name',
        ]);

        $filename = strtolower((string) $deliveryTrip->trip_number) . '.xlsx';

        return response()->streamDownload(function () use ($deliveryTrip): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Perjalanan Kirim');

            $rows = [];
            $rows[] = [__('delivery_trip.title')];
            $rows[] = [__('delivery_trip.trip_number'), $deliveryTrip->trip_number];
            $rows[] = [__('txn.date'), optional($deliveryTrip->trip_date)->format('d-m-Y')];
            $rows[] = [__('delivery_trip.driver_name'), $deliveryTrip->driver_name];
            $rows[] = [__('delivery_trip.vehicle_plate'), $deliveryTrip->vehicle_plate ?: '-'];
            $rows[] = [__('delivery_trip.member_count'), (int) $deliveryTrip->member_count];
            $rows[] = [__('txn.notes'), (string) ($deliveryTrip->notes ?: '-')];
            $rows[] = [];
            $rows[] = [__('delivery_trip.members')];
            $rows[] = [__('txn.no'), __('ui.name')];

            foreach ($deliveryTrip->members->values() as $index => $member) {
                $rows[] = [
                    $index + 1,
                    $member->member_name,
                ];
            }

            $rows[] = [];
            $rows[] = [__('delivery_trip.cost_breakdown')];
            $rows[] = [__('delivery_trip.fuel_cost'), (int) $deliveryTrip->fuel_cost];
            $rows[] = [__('delivery_trip.toll_cost'), (int) $deliveryTrip->toll_cost];
            $rows[] = [__('delivery_trip.meal_cost'), (int) $deliveryTrip->meal_cost];
            $rows[] = [__('delivery_trip.other_cost'), (int) $deliveryTrip->other_cost];
            $rows[] = [__('delivery_trip.total_cost'), (int) $deliveryTrip->total_cost];

            $sheet->fromArray($rows, null, 'A1');

            $memberCount = max(1, $deliveryTrip->members->count());
            $memberHeaderRow = 10;
            ExcelExportStyler::styleTable($sheet, $memberHeaderRow, 2, $memberCount, true);

            $costStartRow = $memberHeaderRow + $memberCount + 5;
            ExcelExportStyler::styleTable($sheet, $costStartRow, 2, 5, true);
            ExcelExportStyler::formatNumberColumns($sheet, $costStartRow + 1, $costStartRow + 5, [2], '#,##0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'trip_date' => ['required', 'date'],
            'driver_name' => ['required', 'string', 'max:120'],
            'vehicle_plate' => ['nullable', 'string', 'max:40'],
            'member_user_ids' => ['nullable', 'array'],
            'member_user_ids.*' => ['integer', 'exists:users,id'],
            'extra_member_names' => ['nullable', 'string', 'max:2000'],
            'fuel_cost' => ['required', 'integer', 'min:0'],
            'toll_cost' => ['required', 'integer', 'min:0'],
            'meal_cost' => ['required', 'integer', 'min:0'],
            'other_cost' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return Collection<int, array{user_id:int|null,member_name:string}>
     */
    private function resolveMembers(array $userIds, string $extraNamesRaw): Collection
    {
        $resolvedUserIds = collect($userIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $members = collect();

        if ($resolvedUserIds->isNotEmpty()) {
            $users = User::query()
                ->whereIn('id', $resolvedUserIds->all())
                ->orderBy('name')
                ->get(['id', 'name']);

            $members = $members->merge(
                $users->map(fn (User $user): array => [
                    'user_id' => (int) $user->id,
                    'member_name' => trim((string) $user->name),
                ])
            );
        }

        $extraNames = preg_split('/[\r\n,;]+/', $extraNamesRaw) ?: [];
        $extraRows = collect($extraNames)
            ->map(fn ($name): string => trim((string) $name))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->map(fn (string $name): array => [
                'user_id' => null,
                'member_name' => substr($name, 0, 120),
            ]);

        return $members
            ->merge($extraRows)
            ->unique(fn (array $row): string => strtolower((string) $row['member_name']))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function teamMemberOptions(): Collection
    {
        return User::query()
            ->select(['id', 'name', 'role'])
            ->orderBy('name')
            ->limit(200)
            ->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function totalCostFromData(array $data): int
    {
        return (int) $data['fuel_cost']
            + (int) $data['toll_cost']
            + (int) $data['meal_cost']
            + (int) $data['other_cost'];
    }

    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function generateTripNumber(string $tripDate): string
    {
        $prefix = 'TRP-' . date('Ymd', strtotime($tripDate));

        $latest = DeliveryTrip::query()
            ->whereDate('trip_date', $tripDate)
            ->where('trip_number', 'like', $prefix . '-%')
            ->lockForUpdate()
            ->max('trip_number');

        $next = 1;
        if (is_string($latest) && $latest !== '') {
            $next = ((int) substr($latest, -4)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}
