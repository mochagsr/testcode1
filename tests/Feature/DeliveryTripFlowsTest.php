<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\DeliveryTrip;
use App\Models\User;
use App\Support\SemesterBookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTripFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_delivery_trip_log_with_assistant_and_costs(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->post(route('delivery-trips.store'), [
            'trip_date' => '2026-02-20',
            'driver_name' => 'Pak Supir',
            'assistant_name' => 'Pak Asisten',
            'vehicle_plate' => 'N 1234 PG',
            'fuel_cost' => 120000,
            'toll_cost' => 50000,
            'meal_cost' => 30000,
            'other_cost' => 10000,
            'notes' => 'Pengiriman area kota',
        ]);

        $response->assertRedirect();

        $trip = DeliveryTrip::query()->first();
        $this->assertNotNull($trip);
        $this->assertSame('Pak Supir', (string) $trip?->driver_name);
        $this->assertSame('Pak Asisten', (string) $trip?->assistant_name);
        $this->assertSame(210000, (int) $trip?->total_cost);
        $this->assertSame(0, (int) $trip?->member_count);
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => DeliveryTrip::class,
            'reference_id' => (int) $trip?->id,
            'entry_type' => 'delivery_trip_create',
        ]);
    }

    public function test_delivery_trip_appears_in_semester_transactions_and_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);

        $trip = DeliveryTrip::query()->create([
            'trip_number' => 'TRP-20260220-0001',
            'trip_date' => now()->format('Y-m-d'),
            'driver_name' => 'Supir Tes',
            'vehicle_plate' => 'N 9999 XX',
            'member_count' => 2,
            'fuel_cost' => 100000,
            'toll_cost' => 20000,
            'meal_cost' => 15000,
            'other_cost' => 5000,
            'total_cost' => 140000,
            'created_by_user_id' => $admin->id,
        ]);

        $semester = app(SemesterBookService::class)->currentSemester();

        $this->actingAs($admin)
            ->get(route('semester-transactions.index', [
                'semester' => $semester,
                'type' => 'delivery_trip',
            ]))
            ->assertOk()
            ->assertSee($trip->trip_number);

        $this->actingAs($admin)
            ->get(route('reports.export.csv', [
                'dataset' => 'delivery_trips',
                'semester' => $semester,
            ]))
            ->assertOk();
    }

    public function test_admin_update_delivery_trip_posts_adjustment_journal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $storeResponse = $this->actingAs($admin)->post(route('delivery-trips.store'), [
            'trip_date' => '2026-02-20',
            'driver_name' => 'Supir A',
            'assistant_name' => 'Asisten A',
            'vehicle_plate' => 'N 1111 AA',
            'fuel_cost' => 100000,
            'toll_cost' => 20000,
            'meal_cost' => 10000,
            'other_cost' => 5000,
            'notes' => 'Awal',
        ]);
        $storeResponse->assertRedirect();

        $trip = DeliveryTrip::query()->firstOrFail();
        $this->assertSame(135000, (int) $trip->total_cost);

        $updateResponse = $this->actingAs($admin)->put(route('delivery-trips.update', $trip), [
            'trip_date' => '2026-02-20',
            'driver_name' => 'Supir A',
            'assistant_name' => 'Asisten B',
            'vehicle_plate' => 'N 1111 AA',
            'fuel_cost' => 120000,
            'toll_cost' => 25000,
            'meal_cost' => 10000,
            'other_cost' => 5000,
            'notes' => 'Koreksi biaya',
        ]);
        $updateResponse->assertRedirect();

        $trip->refresh();
        $this->assertSame(160000, (int) $trip->total_cost);

        $adjustmentEntry = JournalEntry::query()
            ->where('reference_type', DeliveryTrip::class)
            ->where('reference_id', (int) $trip->id)
            ->where('entry_type', 'delivery_trip_adjustment')
            ->first();

        $this->assertNotNull($adjustmentEntry);
    }
}
