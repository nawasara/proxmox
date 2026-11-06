<?php

namespace Nawasara\Proxmox\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Nawasara\Proxmox\Models\ConsoleSession;
use Nawasara\Proxmox\Support\ConsoleRecording;

/**
 * Memutar rekaman isi sesi console dari Riwayat Console.
 *
 * Berkasnya asciicast v2 di storage, ditulis console-recorder. Diakses hanya
 * lewat rute ini (izin proxmox.console.history), tidak pernah lewat URL
 * storage publik: isinya keluaran shell root, bisa memuat data rahasia.
 */
class ConsoleRecordingController extends Controller
{
    public function show(Request $request, int $session)
    {
        $model = ConsoleSession::findOrFail($session);
        $files = ConsoleRecording::files($model->id);
        $part = max(0, min(count($files) - 1, (int) $request->query('part', 0)));

        return response()->view('nawasara-proxmox::console.recording', [
            'session' => $model,
            'parts' => count($files),
            'part' => $part,
            'castUrl' => $files ? route('nawasara-proxmox.console.recording.cast', [$model->id, $part]) : null,
            'retentionDays' => (int) config('nawasara-proxmox.console_recording.retention_days', 90),
        ])->header('Cache-Control', 'no-store');
    }

    public function cast(int $session, int $part)
    {
        $files = ConsoleRecording::files($session);
        abort_unless(isset($files[$part]), 404);

        return response()->file($files[$part], [
            'Content-Type' => 'application/x-asciicast',
            'Cache-Control' => 'no-store',
        ]);
    }
}
