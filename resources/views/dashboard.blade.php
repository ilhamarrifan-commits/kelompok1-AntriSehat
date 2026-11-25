@php
    $tanggal = \Carbon\Carbon::parse($today)->locale('id');

    // Mendukung day_of_week berupa angka (0-6 atau 1-7) maupun nama hari bahasa Inggris
    $namaHari = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        'sunday' => 'Minggu', 'monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu',
        'thursday' => 'Kamis', 'friday' => 'Jumat', 'saturday' => 'Sabtu',
    ];

    $statusAntrean = [
        'waiting'   => ['Menunggu', 'bg-amber-50 text-amber-700 border-amber-200'],
        'called'    => ['Dipanggil', 'bg-blue-50 text-blue-700 border-blue-200'],
        'finished'  => ['Selesai', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'cancelled' => ['Dibatalkan', 'bg-slate-100 text-slate-600 border-slate-200'],
    ];

    $menu = [
        'overview'     => 'Ringkasan Layanan',
        'queues'       => 'Antrean Aktif',
        'appointments' => 'Janji Temu Hari Ini',
        'doctors'      => 'Dokter & Jadwal',
    ];
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Layanan Klinik - AntriSehat</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased md:flex">

    {{-- Header mobile --}}
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between bg-slate-900 px-4 text-white shadow-sm md:hidden">
        <div class="flex items-center gap-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-600 text-sm font-bold">AS</div>
            <span class="text-lg font-bold tracking-tight">AntriSehat</span>
        </div>
        <button id="mobileMenuBtn" type="button" class="rounded-lg p-2 text-slate-300 hover:text-white focus-visible:outline-2 focus-visible:outline-teal-400" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </header>

    {{-- Sidebar --}}
    <aside id="sidebar" class="fixed inset-x-0 top-16 z-20 hidden max-h-[calc(100dvh-4rem)] flex-col overflow-y-auto bg-slate-900 p-5 text-slate-300 shadow-lg md:sticky md:top-0 md:flex md:h-screen md:max-h-none md:w-64 md:shrink-0 md:shadow-none">
        <div class="hidden items-center gap-3 border-b border-slate-800 pb-6 md:flex">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-600 text-base font-bold text-white">AS</div>
            <div>
                <h1 class="text-base font-bold leading-none tracking-tight text-white">AntriSehat</h1>
                <span class="text-xs text-slate-400">Sistem Antrean Klinik</span>
            </div>
        </div>

        <nav class="flex-1 space-y-1 md:mt-6" aria-label="Menu utama">
            @foreach($menu as $anchor => $label)
                <a href="#{{ $anchor }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 transition-colors hover:bg-slate-800 hover:text-white">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="mt-4 border-t border-slate-800 pt-4">
            <div class="rounded-xl bg-slate-800/60 p-3">
                <span class="block text-xs font-medium text-slate-400">Tanggal Operasional</span>
                <span class="mt-0.5 block text-sm font-semibold text-slate-200">{{ $tanggal->translatedFormat('l, d F Y') }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-slate-700 px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white focus-visible:outline-2 focus-visible:outline-teal-400">Keluar</button>
            </form>
        </div>
    </aside>

    {{-- Konten utama --}}
    <main class="mx-auto w-full min-w-0 max-w-7xl flex-1 space-y-6 p-4 md:space-y-8 md:p-8">

        <div class="border-b border-slate-200 pb-4">
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Dashboard Operasional Klinik</h2>
            <p class="mt-1 text-sm text-slate-500">Antrean pasien dan jadwal dokter per {{ $tanggal->translatedFormat('d F Y') }}. Muat ulang halaman untuk data terbaru.</p>
        </div>

        {{-- Ringkasan --}}
        <section id="overview" class="grid scroll-mt-20 grid-cols-2 gap-3 md:gap-6 lg:grid-cols-4">
            @foreach([
                ['Dokter Aktif', $stats['total_doctors'], 'Dokter', 'text-slate-900'],
                ['Total Antrean Hari Ini', $stats['today_queues'], 'Tiket', 'text-slate-900'],
                ['Menunggu Dipanggil', $stats['waiting_queues'], 'Antrean', 'text-amber-600'],
                ['Janji Temu Hari Ini', $stats['today_appointments'], 'Janji', 'text-teal-600'],
            ] as [$judul, $nilai, $satuan, $warna])
                <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $judul }}</span>
                    <div class="mt-2 flex flex-wrap items-baseline justify-between gap-1">
                        <span class="text-3xl font-bold tracking-tight {{ $warna }}">{{ $nilai }}</span>
                        <span class="text-xs text-slate-500">{{ $satuan }}</span>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- Antrean --}}
        <section id="queues" class="scroll-mt-20 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Daftar Antrean Hari Ini</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Diurutkan per poli dan nomor antrean.</p>
                </div>
                <span class="self-start rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-500 sm:self-auto">
                    Total: {{ count($queues) }} antrean
                </span>
            </div>

            @if(count($queues) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm text-slate-700">
                        <thead class="border-b border-slate-100 bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-3">No. Antrean</th>
                                <th scope="col" class="px-5 py-3">Nama Pasien</th>
                                <th scope="col" class="px-5 py-3">Poli</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                                <th scope="col" class="px-5 py-3 text-right">Waktu Panggil</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($queues as $q)
                                @php
                                    [$labelStatus, $warnaStatus] = $statusAntrean[$q->status] ?? [$q->status, 'bg-slate-100 text-slate-600 border-slate-200'];
                                @endphp
                                <tr class="transition-colors hover:bg-slate-50">
                                    <td class="px-5 py-3.5 font-bold text-slate-900">#{{ sprintf('%03d', $q->queue_number) }}</td>
                                    <td class="px-5 py-3.5 font-medium text-slate-800">{{ $q->patient_name }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="rounded-md bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">{{ $q->poly }}</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $warnaStatus }}">{{ $labelStatus }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono text-xs text-slate-500">
                                        {{ $q->called_at ? \Carbon\Carbon::parse($q->called_at)->format('H:i') : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="p-8 text-center text-sm text-slate-500">Belum ada antrean yang terdaftar untuk hari ini.</p>
            @endif
        </section>

        {{-- Janji temu & dokter --}}
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">

            <section id="appointments" class="scroll-mt-20 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Janji Temu Hari Ini</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Daftar booking konsultasi dokter.</p>
                    </div>
                    <span class="shrink-0 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ count($appointments) }} janji</span>
                </div>

                <div class="space-y-3 p-5">
                    @forelse($appointments as $app)
                        @php
                            $jam = $app->start_time ?? $app->schedule?->start_time;
                        @endphp
                        <article class="rounded-lg border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <span class="block break-words text-sm font-semibold text-slate-900">{{ $app->patient_name }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">No. HP: {{ $app->patient_phone }}</span>
                                </div>
                                <span class="shrink-0 rounded border border-teal-200 bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700">{{ $app->status }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs text-slate-600">
                                <span>Dokter: <strong>{{ $app->doctor->name ?? '-' }}</strong></span>
                                <span class="shrink-0 rounded bg-slate-100 px-2 py-0.5 font-mono">Jam {{ $jam ? substr($jam, 0, 5) : '-' }}</span>
                            </div>
                        </article>
                    @empty
                        <p class="py-8 text-center text-sm text-slate-500">Tidak ada janji temu untuk hari ini.</p>
                    @endforelse
                </div>
            </section>

            <section id="doctors" class="scroll-mt-20 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Dokter &amp; Jadwal</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Jadwal praktik dan kuota maksimal.</p>
                    </div>
                    <span class="shrink-0 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ count($doctors) }} dokter</span>
                </div>

                <div class="space-y-3 p-5">
                    @forelse($doctors as $doctor)
                        <article class="rounded-lg border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <span class="min-w-0 break-words text-sm font-semibold text-slate-900">{{ $doctor->name }}</span>
                                <span class="shrink-0 rounded border px-2 py-0.5 text-xs font-medium {{ $doctor->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600' }}">
                                    {{ $doctor->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>

                            <ul class="mt-3 divide-y divide-slate-100 border-t border-slate-100 text-xs text-slate-600">
                                @forelse($doctor->schedules as $schedule)
                                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                        <span class="font-medium text-slate-800">
                                            {{ $namaHari[strtolower((string) $schedule->day_of_week)] ?? $schedule->day_of_week }}
                                        </span>
                                        <span class="font-mono">{{ substr((string) $schedule->start_time, 0, 5) }} - {{ substr((string) $schedule->end_time, 0, 5) }}</span>
                                        <span>Kuota {{ $schedule->max_quota }}</span>
                                    </li>
                                @empty
                                    <li class="pt-3 text-slate-500">Belum ada jadwal praktik.</li>
                                @endforelse
                            </ul>
                        </article>
                    @empty
                        <p class="py-8 text-center text-sm text-slate-500">Belum ada data dokter.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </main>

    <script>
        const menuButton = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');

        function setMenu(open) {
            sidebar.classList.toggle('hidden', !open);
            sidebar.classList.toggle('flex', open);
            menuButton.setAttribute('aria-expanded', String(open));
            menuButton.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
        }

        menuButton.addEventListener('click', () => {
            setMenu(menuButton.getAttribute('aria-expanded') !== 'true');
        });

        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => setMenu(false));
        });
    </script>
</body>
</html>
