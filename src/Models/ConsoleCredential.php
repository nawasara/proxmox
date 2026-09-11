<?php

namespace Nawasara\Proxmox\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kredensial login di dalam container, untuk satu mesin.
 *
 * Dipakai halaman console menjawab prompt `login:` secara otomatis, sehingga
 * pengguna Nawasara tidak perlu mengetiknya sendiri.
 *
 * ⚠️ Ini BUKAN kredensial API Proxmox. Yang itu satu untuk seluruh cluster dan
 * disimpan di Vault (`console_user` / `console_password`).
 */
class ConsoleCredential extends Model
{
    protected $table = 'nawasara_proxmox_console_credentials';

    protected $fillable = [
        'node_name',
        'vmid',
        'vm_name',
        'login_user',
        'login_password',
        'is_active',
    ];

    protected $casts = [
        'vmid' => 'integer',
        'is_active' => 'boolean',

        // Cast bawaan Laravel — terenkripsi dengan APP_KEY, sama seperti Vault.
        'login_password' => 'encrypted',
    ];

    /**
     * Kredensial untuk satu mesin, bila ada dan masih aktif.
     *
     * Mengembalikan null berarti console mesin itu meminta login seperti
     * biasa. Itu keadaan bawaan yang benar, bukan kegagalan — mesin yang
     * pemiliknya belum menyetujui akses otomatis memang harus begitu.
     */
    public static function forVm(string $nodeName, int $vmid): ?self
    {
        return static::query()
            ->where('node_name', $nodeName)
            ->where('vmid', $vmid)
            ->where('is_active', true)
            ->first();
    }
}
