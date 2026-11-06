<?php

namespace Nawasara\Proxmox\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Rekaman isi sesi console, ditulis oleh sidecar `console-recorder`
 * (docker/console-recorder) yang duduk di antara nginx dan Proxmox.
 *
 * Nawasara hanya melakukan tiga hal di sini:
 *
 *  - menandatangani penanda rekaman `<id sesi>.<HMAC>` yang ditempel ke URL
 *    websocket. Perekam menolak sesi tanpa penanda sah (fail-closed), dan
 *    HMAC mencegah orang memakai id sesi lain untuk mengotori rekamannya;
 *  - menemukan berkas rekaman sebuah sesi untuk diputar di panel;
 *  - menghapus rekaman yang lebih tua dari `retention_days` (bawaan 90).
 *
 * Yang direkam hanya KELUARAN terminal, bukan ketikan: sandi yang diketik
 * tidak ter-echo, jadi tidak pernah tersimpan. Metadata sesi (siapa, kapan,
 * berapa lama) tetap di tabel console sessions dan tidak ikut dihapus.
 */
final class ConsoleRecording
{
    public static function enabled(): bool
    {
        return self::secret() !== '';
    }

    /** Tempelkan penanda rekaman ke URL websocket; apa adanya bila rekaman mati. */
    public static function attach(?string $wsUrl, int $sessionId): ?string
    {
        if ($wsUrl === null || ! self::enabled()) {
            return $wsUrl;
        }

        return $wsUrl.(str_contains($wsUrl, '?') ? '&' : '?').'rec='.self::token($sessionId);
    }

    public static function token(int $sessionId): string
    {
        return $sessionId.'.'.hash_hmac('sha256', (string) $sessionId, self::secret());
    }

    public static function dir(): string
    {
        return (string) config('nawasara-proxmox.console_recording.dir', storage_path('app/console-recordings'));
    }

    /**
     * Berkas rekaman satu sesi, terlama dulu. Lebih dari satu bila websocket
     * sesi itu tersambung ulang — perekam tidak pernah menimpa berkas.
     *
     * @return list<string>
     */
    public static function files(int $sessionId): array
    {
        $found = glob(self::dir().'/'.$sessionId.'-*.cast') ?: [];
        sort($found, SORT_NATURAL);

        return array_values($found);
    }

    /** Hapus rekaman lebih tua dari retention_days. Mengembalikan jumlahnya. */
    public static function prune(): int
    {
        $days = max(1, (int) config('nawasara-proxmox.console_recording.retention_days', 90));
        $cutoff = now()->subDays($days)->getTimestamp();
        $deleted = 0;

        foreach (glob(self::dir().'/*.cast') ?: [] as $file) {
            if (@filemtime($file) < $cutoff && File::delete($file)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            Log::info("[proxmox] {$deleted} rekaman console lebih dari {$days} hari dihapus");
        }

        return $deleted;
    }

    private static function secret(): string
    {
        return (string) config('nawasara-proxmox.console_recording.secret', '');
    }
}
