<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SpkStage extends Model
{
    protected $table = 'spk_stage';

    public const TAHAP_CTCP = 'ctcp';
    public const TAHAP_WEB = 'web';
    public const TAHAP_SHEET = 'sheet';
    public const TAHAP_FINISHING = 'finishing';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'spk_id', 'tahap', 'pic', 'mesin',
        'tanggal_rencana', 'tanggal_realisasi', 'qty_rencana', 'qty_realisasi', 'selesai', 'urutan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_rencana' => 'date',
            'tanggal_realisasi' => 'date',
            'qty_rencana' => 'integer',
            'qty_realisasi' => 'integer',
            'selesai' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Spk, $this>
     */
    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class);
    }

    /**
     * Display status for the stage row.
     */
    public function computedStatus(): string
    {
        if ($this->selesai) {
            return Spk::STATUS_SELESAI;
        }

        $today = Carbon::today();
        if ($this->tanggal_rencana !== null && $this->tanggal_rencana->lte($today)) {
            return Spk::STATUS_PROSES;
        }

        return Spk::STATUS_ANTRE;
    }

    /**
     * Selisih realisasi - rencana (null jika belum ada realisasi).
     */
    public function deviasi(): ?int
    {
        if ($this->qty_rencana === null || $this->qty_realisasi === null) {
            return null;
        }

        return (int) $this->qty_realisasi - (int) $this->qty_rencana;
    }
}
