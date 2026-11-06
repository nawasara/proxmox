<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Rekaman console — {{ $session->vm_name }}</title>
    {{-- asciinema-player dari CDN, versi dipatok (sama seperti xterm.js di console). --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/asciinema-player@3.8.0/dist/bundle/asciinema-player.css" />
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #0a0a0a; color: #e5e5e5; font-family: ui-sans-serif, system-ui, sans-serif; }
        .bar { display: flex; flex-wrap: wrap; gap: 6px 18px; align-items: center; padding: 12px 18px; border-bottom: 1px solid #262626; font-size: 13px; }
        .bar b { color: #fff; font-weight: 600; }
        .muted { color: #a3a3a3; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; background: #262626; color: #d4d4d4; }
        .pill.kisara { background: #0c4a6e; color: #bae6fd; }
        .wrap { padding: 18px; }
        .note { margin-top: 12px; font-size: 12px; color: #a3a3a3; line-height: 1.6; }
        .parts a { color: #93c5fd; margin-right: 10px; }
        .empty { padding: 48px 18px; text-align: center; color: #a3a3a3; }
    </style>
</head>
<body>
    @php $tz = config('app.display_timezone', 'Asia/Jakarta'); @endphp
    <div class="bar">
        <span><b>{{ $session->vm_name }}</b> <span class="muted">{{ $session->vm_type === 'lxc' ? 'LXC' : 'QEMU' }} {{ $session->vmid }} · {{ $session->node_name }}</span></span>
        <span>{{ $session->user_name }} <span class="muted">{{ $session->user_email }}</span></span>
        <span class="pill {{ $session->channel === 'kisara' ? 'kisara' : '' }}">{{ $session->channel === 'kisara' ? 'Akun Kisara' : 'Panel' }}</span>
        <span class="muted">{{ $session->started_at?->setTimezone($tz)->translatedFormat('d M Y H:i') }} · {{ $session->duration_label }}</span>
        @if ($session->ip_address)<span class="muted">IP {{ $session->ip_address }}</span>@endif
    </div>

    @if ($castUrl)
        <div class="wrap">
            @if ($parts > 1)
                <p class="parts muted">Sesi ini tersambung {{ $parts }} kali:
                    @for ($i = 0; $i < $parts; $i++)
                        <a href="?part={{ $i }}">{{ $i === $part ? '▶ ' : '' }}bagian {{ $i + 1 }}</a>
                    @endfor
                </p>
            @endif
            <div id="player"></div>
            <p class="note">
                Yang terekam adalah keluaran terminal, termasuk perintah yang tampil di layar.
                Ketikan yang tidak tampil (misalnya kata sandi) tidak direkam.
                Rekaman dihapus otomatis setelah {{ $retentionDays }} hari; catatan sesinya tetap disimpan.
            </p>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/asciinema-player@3.8.0/dist/bundle/asciinema-player.min.js"></script>
        <script>
            AsciinemaPlayer.create(@json($castUrl), document.getElementById('player'), {
                fit: 'width', idleTimeLimit: 2, theme: 'monokai', terminalFontSize: '14px',
            });
        </script>
    @else
        <div class="empty">
            Tidak ada rekaman untuk sesi ini. Sesi sebelum perekaman aktif tidak punya rekaman,
            dan rekaman lebih dari {{ $retentionDays }} hari sudah dihapus otomatis.
        </div>
    @endif
</body>
</html>
