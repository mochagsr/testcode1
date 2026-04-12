<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $rows = collect([
            $this->normalizeRow('exp|Exemplar'),
        ]);

        $rows = $rows
            ->merge($this->rowsFromSetting('product_unit_options'))
            ->merge($this->rowsFromSetting('outgoing_unit_options'))
            ->merge($this->rowsFromColumn('products', 'unit'))
            ->merge($this->rowsFromColumn('outgoing_transaction_items', 'unit'))
            ->filter(fn (array $row): bool => $row['code'] !== '' && $row['name'] !== '')
            ->unique('code')
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('product_units')->upsert($rows, ['code'], ['name', 'updated_at']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{code:string,name:string,description:null,created_at:string,updated_at:string}>
     */
    private function rowsFromSetting(string $key): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('app_settings')) {
            return collect();
        }

        $raw = (string) (DB::table('app_settings')->where('key', $key)->value('value') ?? '');
        if ($raw === '') {
            return collect();
        }

        return collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(fn (string $item): array => $this->normalizeRow($item));
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{code:string,name:string,description:null,created_at:string,updated_at:string}>
     */
    private function rowsFromColumn(string $table, string $column): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return collect();
        }

        return collect(DB::table($table)->whereNotNull($column)->distinct()->pluck($column)->all())
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(fn (string $item): array => $this->normalizeRow($item));
    }

    /**
     * @return array{code:string,name:string,description:null,created_at:string,updated_at:string}
     */
    private function normalizeRow(string $value): array
    {
        $rawCode = $value;
        $rawName = $value;
        if (str_contains($value, '|')) {
            [$rawCode, $rawName] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');
        }

        $code = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', (string) $rawCode));
        if ($code === '' && $rawName !== '') {
            $code = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', (string) $rawName));
        }

        $name = trim($rawName) !== '' ? trim($rawName) : ucfirst($code);
        $timestamp = now()->format('Y-m-d H:i:s');

        return [
            'code' => substr($code, 0, 30),
            'name' => substr($name, 0, 120),
            'description' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
};
