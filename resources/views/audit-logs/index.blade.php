<x-app-layout :title="'Log Aktivitas — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Log Aktivitas</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Riwayat aksi sensitif — perubahan role, hapus akun, ubah pengaturan, transisi status layanan/Purchase Order.</p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-300 p-4 dark:border-gray-700">
            <form method="GET" action="{{ route('audit-logs.index') }}" class="flex flex-1 flex-wrap items-center gap-3">
                <select
                    name="action"
                    onchange="this.form.submit()"
                    class="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">Semua Aksi</option>
                    @foreach ($actions as $value)
                        <option value="{{ $value }}" @selected($action === $value)>{{ $value }}</option>
                    @endforeach
                </select>

                @if ($action !== '')
                    <a href="{{ route('audit-logs.index') }}" class="text-sm font-semibold text-gray-500 hover:text-primary dark:text-gray-400">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Aktor</th>
                        <th class="px-4 py-3">Aksi</th>
                        <th class="px-4 py-3">Deskripsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3">
                                @if ($log->actor)
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $log->actor->name }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[13px] font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-300">Sistem</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-primary-light px-3 py-1 text-[13px] font-semibold text-primary dark:bg-primary/10">{{ $log->action }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $log->description }}
                                @if (! empty($log->changes))
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-xs font-semibold text-gray-400 hover:text-primary">Rincian</summary>
                                        <ul class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            @foreach ($log->changes as $key => $value)
                                                <li><span class="font-semibold">{{ $key }}:</span> {{ is_scalar($value) ? $value : json_encode($value) }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada entry log aktivitas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
