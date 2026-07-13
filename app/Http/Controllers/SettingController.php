<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingUpdateRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingController extends Controller
{
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
            Setting::query()->where('id', $id)->update([
                'value' => $value,
                'updated_by' => Auth::id(),
            ]);
        }

        return redirect()->route('settings.index')->with('status', 'Pengaturan berhasil disimpan.');
    }
}
