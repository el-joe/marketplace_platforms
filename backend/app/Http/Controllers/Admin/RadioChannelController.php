<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RadioChannelType;
use App\Enums\RadioScheduleSlotRecurrence;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\RadioChannel;
use App\Models\RadioScheduleSlot;
use App\Services\RadioSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RadioChannelController extends Controller
{
    // ── Channels ──────────────────────────────────────────────────────────────

    public function index(): View
    {
        $channels = RadioChannel::with('country')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.radio.channels.index', compact('channels'));
    }

    public function create(): View
    {
        $countries = Country::orderBy('name_en')->get();
        return view('admin.radio.channels.form', compact('countries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_en'             => 'required|string|max:150',
            'name_ar'             => 'required|string|max:150',
            'type'                => ['required', Rule::enum(RadioChannelType::class)],
            'stream_url'          => 'nullable|url|max:500',
            'fallback_media_path' => 'nullable|string|max:255',
            'thumbnail_path'      => 'nullable|string|max:255',
            'country_id'          => 'nullable|exists:countries,id',
            'is_active'           => 'boolean',
        ]);

        RadioChannel::create($data);

        return redirect()->route('admin.radio.channels.index')
            ->with('success', 'Radio channel created.');
    }

    public function edit(RadioChannel $channel): View
    {
        $countries = Country::orderBy('name_en')->get();
        return view('admin.radio.channels.form', compact('channel', 'countries'));
    }

    public function update(Request $request, RadioChannel $channel): RedirectResponse
    {
        $data = $request->validate([
            'name_en'             => 'required|string|max:150',
            'name_ar'             => 'required|string|max:150',
            'type'                => ['required', Rule::enum(RadioChannelType::class)],
            'stream_url'          => 'nullable|url|max:500',
            'fallback_media_path' => 'nullable|string|max:255',
            'thumbnail_path'      => 'nullable|string|max:255',
            'country_id'          => 'nullable|exists:countries,id',
            'is_active'           => 'boolean',
        ]);

        $channel->update($data);

        return redirect()->route('admin.radio.channels.index')
            ->with('success', 'Radio channel updated.');
    }

    public function destroy(RadioChannel $channel): RedirectResponse
    {
        $channel->delete();
        return redirect()->route('admin.radio.channels.index')
            ->with('success', 'Radio channel deleted.');
    }

    // ── Schedule ──────────────────────────────────────────────────────────────

    public function schedule(RadioChannel $channel): View
    {
        return view('admin.radio.schedule', compact('channel'));
    }

    /** FullCalendar feed: returns slots as events JSON */
    public function scheduleEvents(RadioChannel $channel, RadioSchedulingService $service): JsonResponse
    {
        $upcoming = $service->getUpcomingSchedule($channel, 60);

        $events = $upcoming->map(fn($occ) => [
            'id'    => $occ['slot']->id,
            'title' => $occ['slot']->title,
            'start' => $occ['starts_at']->toIso8601String(),
            'end'   => $occ['ends_at']->toIso8601String(),
            'color' => $occ['slot']->recurrence === RadioScheduleSlotRecurrence::Once ? '#6366f1' : '#0ea5e9',
            'extendedProps' => [
                'recurrence'      => $occ['slot']->recurrence?->value,
                'recurrence_days' => $occ['slot']->recurrence_days,
                'is_active'       => $occ['slot']->is_active,
            ],
        ]);

        return response()->json($events);
    }

    public function storeSlot(Request $request, RadioChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'starts_at'       => 'required|date',
            'ends_at'         => 'required|date|after:starts_at',
            'recurrence'      => ['required', Rule::enum(RadioScheduleSlotRecurrence::class)],
            'recurrence_days' => 'nullable|array',
            'recurrence_days.*' => 'in:mon,tue,wed,thu,fri,sat,sun',
            'is_active'       => 'boolean',
        ]);

        $slot = $channel->scheduleSlots()->create([
            ...$data,
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        return response()->json(['slot' => $slot], 201);
    }

    public function updateSlot(Request $request, RadioChannel $channel, RadioScheduleSlot $slot): JsonResponse
    {
        abort_unless($slot->radio_channel_id === $channel->id, 404);

        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'starts_at'       => 'required|date',
            'ends_at'         => 'required|date|after:starts_at',
            'recurrence'      => ['required', Rule::enum(RadioScheduleSlotRecurrence::class)],
            'recurrence_days' => 'nullable|array',
            'recurrence_days.*' => 'in:mon,tue,wed,thu,fri,sat,sun',
            'is_active'       => 'boolean',
        ]);

        $slot->update($data);

        return response()->json(['slot' => $slot]);
    }

    public function destroySlot(RadioChannel $channel, RadioScheduleSlot $slot): JsonResponse
    {
        abort_unless($slot->radio_channel_id === $channel->id, 404);
        $slot->delete();
        return response()->json(['ok' => true]);
    }
}
