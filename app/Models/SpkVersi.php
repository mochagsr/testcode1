<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpkVersi extends Model
{
    protected $table = 'spk_versi';

    /**
     * @var list<string>
     */
    protected $fillable = ['spk_id', 'nama', 'urutan'];

    /**
     * @return BelongsTo<Spk, $this>
     */
    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class);
    }
}
