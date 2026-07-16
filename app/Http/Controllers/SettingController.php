<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingUpdateRequest;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(): View
    {
        $this->authorize('viewAny', Setting::class);

        $groups = Setting::query()->orderBy('group')->orderBy('id')->get()->groupBy('group');

        return view('settings.index', ['groups' => $groups]);
    }

    public function update(SettingUpdateRequest $request): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        foreach ($request->validated('settings') as $id => $value) {
            $setting = Setting::query()->find($id);

            // Skip no-op submit (nilai tidak berubah) — tidak perlu entry
            // audit log untuk resubmit form tanpa perubahan apa pun.
            if ($setting === null || (string) $setting->value === (string) $value) {
                continue;
            }

            $oldValue = $setting->value;

            $setting->update([
                'value' => $value,
                'updated_by' => Auth::id(),
            ]);

            $this->auditLogService->record(
                'settings.updated',
                $setting,
                "Mengubah pengaturan \"{$setting->label}\" dari {$oldValue} menjadi {$value}.",
                ['from' => $oldValue, 'to' => (string) $value],
            );
        }

        return redirect()->route('settings.index')->with('status', 'Pengaturan berhasil disimpan.');
    }
}
