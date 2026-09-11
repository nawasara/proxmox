<?php

namespace Nawasara\Proxmox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali seseorang membuka console sebuah VM.
 *
 * Console memakai satu kredensial bersama, sehingga di sisi Proxmox setiap
 * sesi tampak sebagai root. Baris inilah yang menyimpan siapa sebenarnya.
 */
class ConsoleSession extends Model
{
    protected $table = 'nawasara_proxmox_console_sessions';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'user_nip',
        'node_name',
        'vmid',
        'vm_name',
        'vm_type',
        'started_at',
        'ended_at',
        'duration_seconds',
        'last_seen_at',
        'ip_address',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'vmid' => 'integer',
        'duration_seconds' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /** Sesi yang belum ditutup. */
    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Sesi terbuka yang sudah lama tidak berdenyut.
     *
     * Halaman console berdenyut tiap menit. Diam lebih dari lima menit berarti
     * tabnya sudah tidak ada — ditutup paksa, jaringan putus, atau peramban
     * berhenti. Bukan galat, tetapi tidak boleh dihitung sebagai sesi hidup.
     */
    public function isStale(): bool
    {
        return $this->isOpen()
            && $this->last_seen_at !== null
            && $this->last_seen_at->lt(now()->subMinutes(5));
    }

    /**
     * Durasi untuk ditampilkan.
     *
     * Sesi yang masih terbuka dihitung sampai sekarang; yang sudah ditutup
     * memakai angka tersimpan. Sesi menggantung memakai denyut terakhir —
     * menghitungnya sampai sekarang akan menyatakan orang masih berada di
     * dalam mesin padahal tabnya sudah lama tertutup.
     */
    public function getDurationLabelAttribute(): string
    {
        $detik = $this->duration_seconds;

        if ($detik === null) {
            $akhir = $this->isStale() ? $this->last_seen_at : now();
            $detik = $this->started_at ? (int) $this->started_at->diffInSeconds($akhir) : 0;
        }

        if ($detik < 60) {
            return $detik.' detik';
        }

        $menit = intdiv($detik, 60);

        if ($menit < 60) {
            return $menit.' menit';
        }

        $jam = intdiv($menit, 60);
        $sisaMenit = $menit % 60;

        return $sisaMenit > 0 ? "{$jam} jam {$sisaMenit} menit" : "{$jam} jam";
    }

    /** Nama beserta NIP bila sudah ada. */
    public function getUserLabelAttribute(): string
    {
        return $this->user_nip
            ? $this->user_name.' ('.$this->user_nip.')'
            : $this->user_name;
    }
}
