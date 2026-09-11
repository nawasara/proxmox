{{-- Disusun sebagai variabel, bukan langsung di @json([...]) multi-baris:
     Blade mengurai isi direktif sebagai satu ekspresi dan tersandung pada
     kurung siku yang membentang beberapa baris
     ("Unclosed '[' ... does not match ')'"). --}}
@php
    $cfg = [
        'wsUrl' => $wsUrl,
        'sessionTicket' => $sessionTicket,
        'consoleUser' => $consoleUser,
        'vmName' => $vmName,
    ];
@endphp
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Console — {{ $vmName }}</title>

    {{-- xterm.js dari CDN, versi dipatok. Sama seperti nawasara/teleport;
         bila suatu saat butuh jalan tanpa internet, bundel lewat Vite. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xterm/xterm@5.5.0/css/xterm.css" />
    <script src="https://cdn.jsdelivr.net/npm/@xterm/xterm@5.5.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@xterm/addon-fit@0.10.0/lib/addon-fit.js"></script>

    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; background: #000; }
        body { display: flex; flex-direction: column; font-family: ui-sans-serif, system-ui, sans-serif; }

        .bar {
            display: flex; align-items: center; gap: 12px;
            padding: 8px 14px; background: #171717;
            border-bottom: 1px solid #262626; color: #e5e7eb; font-size: 13px;
            flex: 0 0 auto;
        }
        .bar .nama { font-weight: 600; }
        .bar .rinci { color: #737373; font-size: 12px; }
        .bar .sisa { margin-left: auto; display: flex; align-items: center; gap: 10px; }

        .status { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; }
        .status::before {
            content: ''; width: 8px; height: 8px; border-radius: 50%;
            background: #737373;
        }
        .status.tersambung::before { background: #10b981; }
        .status.putus::before { background: #ef4444; }

        button {
            background: #262626; color: #e5e7eb; border: 1px solid #404040;
            border-radius: 6px; padding: 5px 12px; font-size: 12px; cursor: pointer;
        }
        button:hover { background: #333; }

        .wadah { flex: 1 1 auto; min-height: 0; padding: 6px; }
        .wadah .xterm { height: 100%; }
        .wadah .xterm-viewport::-webkit-scrollbar { width: 8px; }
        .wadah .xterm-viewport::-webkit-scrollbar-thumb { background: #404040; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="bar">
        <span class="nama">{{ $vmName }}</span>
        <span class="rinci">{{ $node }} · {{ $type === 'lxc' ? 'LXC' : 'QEMU' }} {{ $vmid }}</span>
        <div class="sisa">
            <span class="status" id="status">Menyambungkan…</span>
            <button id="tutup" type="button">Tutup</button>
        </div>
    </div>

    <div class="wadah" id="wadah"></div>

    <script>
        (function () {
            const cfg = @json($cfg);

            const elStatus = document.getElementById('status');
            const elWadah = document.getElementById('wadah');

            const setStatus = (teks, kelas) => {
                elStatus.textContent = teks;
                elStatus.className = 'status' + (kelas ? ' ' + kelas : '');
            };

            document.getElementById('tutup').onclick = () => window.close();

            const term = new Terminal({
                cursorBlink: true,
                fontFamily: 'ui-monospace, Menlo, Monaco, "Courier New", monospace',
                fontSize: 14,
                scrollback: 5000,
                theme: {
                    background: '#000000', foreground: '#e5e7eb',
                    cursor: '#10b981', cursorAccent: '#000000',
                    red: '#ef4444', green: '#10b981', yellow: '#f59e0b',
                    blue: '#3b82f6', magenta: '#a855f7', cyan: '#06b6d4',
                    white: '#e5e7eb', brightBlack: '#525252',
                },
            });

            const fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            term.open(elWadah);
            fitAddon.fit();
            term.focus();

            let ws = null;
            let siapKirim = false;

            /**
             * ⚠️ Protokol termproxy Proxmox BUKAN aliran byte polos, dan bukan
             * pula JSON seperti sidecar teleport.
             *
             * Setiap pesan berbentuk teks dengan awalan:
             *   "0:<panjang>:<data>"  → masukan/keluaran terminal
             *   "1:<kolom>:<baris>:"  → perubahan ukuran jendela
             *   "2"                   → denyut nadi, wajib dikirim berkala
             *
             * Dan sebelum apa pun boleh dikirim, klien HARUS mengirim baris
             * autentikasi berisi nama pengguna dan tiket sesi. Tanpa langkah
             * itu Proxmox menutup sambungan tanpa pesan apa pun — kegagalannya
             * terlihat seperti jaringan putus, bukan seperti autentikasi
             * ditolak.
             */
            const kirimData = (data) => {
                if (!ws || ws.readyState !== WebSocket.OPEN || !siapKirim) return;
                ws.send('0:' + data.length + ':' + data);
            };

            const kirimUkuran = () => {
                if (!ws || ws.readyState !== WebSocket.OPEN || !siapKirim) return;
                ws.send('1:' + term.cols + ':' + term.rows + ':');
            };

            term.onData(kirimData);

            {{-- Subprotokol 'binary' WAJIB disebut.
                 Proxmox menjawab baris autentikasi dengan frame BINER
                 (opcode 2), bukan teks — diperiksa langsung terhadap
                 PVE 8.4.16. --}}
            ws = new WebSocket(cfg.wsUrl, 'binary');
            ws.binaryType = 'arraybuffer';

            ws.onopen = () => {
                {{-- Baris autentikasi: "<user>:<tiket>\n".

                     ⚠️ Nama pengguna dikirim dari server, TIDAK dipotong dari
                     tiket di sini. Tiket berbentuk "PVE:root@pam:HEX::TANDA",
                     sehingga split(':')[1] kebetulan benar untuk root@pam dan
                     salah untuk realm lain.

                     ⚠️ Cookie PVEAuthCookie TIDAK terkirim browser: halaman ini
                     dilayani dari nawasara.ponorogo.go.id sementara websocket
                     menuju host Proxmox, jadi cookie-nya lintas-domain. Baris
                     autentikasi inilah satu-satunya cara Proxmox mengenali
                     sesi di jalur ini. --}}
                ws.send(cfg.consoleUser + ':' + cfg.sessionTicket + '\n');
            };

            ws.onmessage = (ev) => {
                let teks = typeof ev.data === 'string'
                    ? ev.data
                    : new TextDecoder().decode(new Uint8Array(ev.data));

                // Balasan "OK" atas baris autentikasi — sesudah ini barulah
                // terminal boleh dipakai.
                if (!siapKirim) {
                    if (teks.startsWith('OK')) {
                        siapKirim = true;
                        setStatus('Tersambung', 'tersambung');
                        kirimUkuran();

                        // Denyut nadi tiap 30 detik. Tanpa ini Proxmox memutus
                        // sambungan yang menganggur, dan bagi pemakai itu
                        // tampak seperti terminal membeku begitu saja.
                        setInterval(() => {
                            if (ws && ws.readyState === WebSocket.OPEN && siapKirim) {
                                ws.send('2');
                            }
                        }, 30000);
                        return;
                    }
                    // Bukan OK — autentikasi ditolak.
                    setStatus('Autentikasi ditolak', 'putus');
                    term.write('\r\n\x1b[31m[Proxmox menolak tiket console]\x1b[0m\r\n');
                    return;
                }

                term.write(teks);
            };

            ws.onerror = () => setStatus('Galat sambungan', 'putus');

            ws.onclose = () => {
                setStatus('Terputus', 'putus');
                term.write('\r\n\x1b[33m[sesi berakhir — buka console lagi dari daftar VM]\x1b[0m\r\n');
            };

            // Menyesuaikan ukuran saat jendela diubah; PTY di sisi sana ikut
            // diberi tahu supaya tampilan tidak terpotong.
            new ResizeObserver(() => {
                try { fitAddon.fit(); } catch (e) { /* diabaikan */ }
                kirimUkuran();
            }).observe(elWadah);
        })();
    </script>
</body>
</html>
