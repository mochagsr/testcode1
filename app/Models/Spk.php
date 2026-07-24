<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Spk extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'spk';

    public const STATUS_ANTRE = 'antre';
    public const STATUS_PROSES = 'proses';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_TELAT = 'telat';

    public const JENIS_PENUH = 'penuh';
    public const JENIS_SEBAGIAN = 'sebagian';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'no_spk', 'nomor_urut', 'semester_periode',
        'konsumen', 'alamat', 'jenis_order', 'tanggal_order', 'deadline_kirim',
        'pakai_web', 'pakai_sheet', 'jenis_cetak', 'revisi_bagian',
        'finishing', 'packing', 'ukuran_jadi', 'mesin_cover', 'catatan',
        'web_kertas', 'web_warna', 'web_mesin', 'web_waste',
        'sheet_kertas', 'sheet_warna', 'sheet_mesin', 'sheet_waste',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_order' => 'date',
            'deadline_kirim' => 'date',
            'pakai_web' => 'boolean',
            'pakai_sheet' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SpkItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SpkItem::class)->orderBy('urutan')->orderBy('id');
    }

    /**
     * @return HasMany<SpkVersi, $this>
     */
    public function versis(): HasMany
    {
        return $this->hasMany(SpkVersi::class)->orderBy('urutan')->orderBy('id');
    }

    /**
     * @return HasMany<SpkStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(SpkStage::class)->orderBy('urutan')->orderBy('id');
    }

    /**
     * @return HasMany<SpkPenanggungJawab, $this>
     */
    public function penanggungJawabs(): HasMany
    {
        return $this->hasMany(SpkPenanggungJawab::class)->orderBy('urutan')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Computed status (§5): antre / proses / selesai / telat. Never stored.
     */
    public function computedStatus(): string
    {
        /** @var Collection<int, SpkStage> $stages */
        $stages = $this->stages;

        if ($stages->isNotEmpty() && $stages->every(fn (SpkStage $s): bool => (bool) $s->selesai)) {
            return self::STATUS_SELESAI;
        }

        $today = Carbon::today();
        if ($this->deadline_kirim !== null && $this->deadline_kirim->lt($today)) {
            return self::STATUS_TELAT;
        }

        $started = $stages->contains(function (SpkStage $s) use ($today): bool {
            return (bool) $s->selesai
                || ($s->tanggal_rencana !== null && $s->tanggal_rencana->lte($today));
        });

        return $started ? self::STATUS_PROSES : self::STATUS_ANTRE;
    }

    /**
     * Progress 0..1 = tahap selesai / total tahap.
     */
    public function progress(): float
    {
        /** @var Collection<int, SpkStage> $stages */
        $stages = $this->stages;
        $total = $stages->count();
        if ($total === 0) {
            return 0.0;
        }

        $done = $stages->filter(fn (SpkStage $s): bool => (bool) $s->selesai)->count();

        return $done / $total;
    }

    public function totalCetakWeb(): int
    {
        return (int) $this->items->sum(fn (SpkItem $item): int => (int) ($item->cetak_isi ?? 0));
    }

    public function totalCetakSheet(): int
    {
        return (int) $this->items->sum(fn (SpkItem $item): int => (int) ($item->cetak_sheet ?? 0));
    }
}
