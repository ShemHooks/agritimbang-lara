<?php

namespace App\Services;

use App\Models\AuctionHouse;
use App\Models\AuctionRegistration;
use App\Models\AuctionSchedule;
use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\User;
use App\Notifications\SystemAlertNotification;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuctionScheduleService
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    private function encoderMunicipality(User $user): string
    {
        $user->loadMissing(['roles', 'lguOfficerProfile']);
        abort_unless(
            $user->status === 'active'
                && $user->verification_status === 'verified'
                && $user->roles->contains('slug', 'lgu_encoder')
                && $user->lguOfficerProfile?->municipality_id,
            403,
            'An active, verified LGU Encoder with an assigned municipality is required.'
        );

        return (string) $user->lguOfficerProfile->municipality_id;
    }

    private function assertEncoderAssignedHouse(User $encoder, string $auctionHouseId): void
    {
        $assigned = DB::table('auction_house_encoder_assignments')
            ->where('auction_house_id', $auctionHouseId)
            ->where('user_id', $encoder->id)
            ->where('is_active', true)
            ->exists();

        abort_unless(
            $assigned,
            403,
            'You are not assigned to this auction house. Ask your LGU Authority to assign you before managing its auction schedules.'
        );
    }

    private function farmerProfile(User $user, bool $requireVerified = true): FarmerProfile
    {
        $user->loadMissing(['roles', 'farmerProfile']);
        abort_unless($user->status === 'active' && $user->roles->contains('slug', 'farmer'), 403, 'An active Farmer account is required.');

        if ($requireVerified && $user->verification_status !== 'verified') {
            throw ValidationException::withMessages([
                'verification' => 'Your Farmer account must be verified before registering livestock for an auction.',
            ]);
        }

        if (!$user->farmerProfile) {
            throw ValidationException::withMessages([
                'profile' => 'Complete your Farmer profile before accessing auction registration.',
            ]);
        }

        return $user->farmerProfile;
    }

    public function encoderVenues(User $encoder)
    {
        $municipalityId = $this->encoderMunicipality($encoder);

        return AuctionHouse::query()
            ->join('auction_house_encoder_assignments as assignments', 'assignments.auction_house_id', '=', 'auction_houses.id')
            ->where('assignments.user_id', $encoder->id)
            ->where('assignments.is_active', true)
            ->where('auction_houses.municipality_id', $municipalityId)
            ->where('auction_houses.is_active', true)
            ->select('auction_houses.*')
            ->with('barangay')
            ->orderBy('name')
            ->get()
            ->filter(fn (AuctionHouse $house) => $house->barangay_id !== null && $house->barangay?->is_active)
            ->values();
    }

    private function resolveVenue(string $municipalityId, string $auctionHouseId, ?User $encoder = null): AuctionHouse
    {
        $house = AuctionHouse::query()
            ->whereKey($auctionHouseId)
            ->where('municipality_id', $municipalityId)
            ->where('is_active', true)
            ->with('barangay')
            ->first();

        if (!$house || !$house->barangay_id || !$house->barangay || !$house->barangay->is_active || (string) $house->barangay->municipality_id !== $municipalityId) {
            throw ValidationException::withMessages([
                'auction_house_id' => 'Select an active auction house in your municipality with an assigned barangay.',
            ]);
        }

        if ($encoder !== null) {
            $this->assertEncoderAssignedHouse($encoder, (string) $house->id);
        }

        return $house;
    }

    private function manilaDateTime(mixed $value): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->setTimezone('Asia/Manila');
        }
        return Carbon::parse((string) $value, 'Asia/Manila')->setTimezone('Asia/Manila');
    }

    private function utcDateTime(mixed $value): Carbon
    {
        return $this->manilaDateTime($value)->utc();
    }

    private function validateScheduleWindow(array $data): void
    {
        $auctionDate = Carbon::parse($data['auction_date'])->toDateString();
        $startTime = substr((string) $data['start_time'], 0, 5);
        $endTime = substr((string) ($data['expected_end_time'] ?? ''), 0, 5);
        $auctionStart = Carbon::parse($auctionDate.' '.$startTime, 'Asia/Manila');
        $opens = $this->manilaDateTime($data['registration_opens_at']);
        $closes = $this->manilaDateTime($data['registration_closes_at']);

        if ($endTime === '') {
            throw ValidationException::withMessages([
                'expected_end_time' => 'Enter the expected auction end time so the schedule can be completed automatically.',
            ]);
        }

        if ($endTime <= $startTime) {
            throw ValidationException::withMessages([
                'expected_end_time' => 'Expected end time must be later than the start time on the same auction date.',
            ]);
        }

        if ($opens->greaterThanOrEqualTo($closes)) {
            throw ValidationException::withMessages([
                'registration_closes_at' => 'Registration closing must be later than registration opening.',
            ]);
        }

        if ($closes->greaterThanOrEqualTo($auctionStart)) {
            throw ValidationException::withMessages([
                'registration_closes_at' => 'Registration must close before (not at) the auction start time.',
            ]);
        }
    }

    /** Prevent overlapping events at the same auction house. Drafts reserve the time slot too. */
    private function assertNoVenueOverlap(string $auctionHouseId, array $data, ?string $ignoreScheduleId = null): void
    {
        $date = Carbon::parse($data['auction_date'])->toDateString();
        $start = substr((string) $data['start_time'], 0, 5).':00';
        $end = substr((string) $data['expected_end_time'], 0, 5).':00';

        $conflict = AuctionSchedule::query()
            ->where('auction_house_id', $auctionHouseId)
            ->whereDate('auction_date', $date)
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $end)
            ->whereRaw("COALESCE(expected_end_time, '23:59:59') > ?", [$start])
            ->when($ignoreScheduleId, fn ($query) => $query->where('id', '!=', $ignoreScheduleId))
            ->first();

        if ($conflict) {
            throw ValidationException::withMessages([
                'auction_date' => "This Auction House already has an auction scheduled from {$conflict->start_time} to ".($conflict->expected_end_time ?? 'the end of the day')." on this date. Choose another time or venue.",
            ]);
        }
    }

    private function scheduleStart(AuctionSchedule $schedule): Carbon
    {
        return Carbon::parse($schedule->auction_date->toDateString().' '.substr((string) $schedule->start_time, 0, 8), 'Asia/Manila')->utc();
    }

    private function scheduleEnd(AuctionSchedule $schedule): Carbon
    {
        $endTime = $schedule->expected_end_time ?: '23:59:59';
        return Carbon::parse($schedule->auction_date->toDateString().' '.substr((string) $endTime, 0, 8), 'Asia/Manila')->utc();
    }

    /** Move published schedules to ongoing/completed using the event's actual date and times. */
    private function syncScheduleLifecycle(AuctionSchedule $schedule): AuctionSchedule
    {
        if (!in_array($schedule->status, ['published', 'ongoing'], true)) {
            return $schedule;
        }

        $now = now('UTC');
        $start = $this->scheduleStart($schedule);
        $end = $this->scheduleEnd($schedule);
        $target = $now->greaterThanOrEqualTo($end)
            ? 'completed'
            : ($now->greaterThanOrEqualTo($start) ? 'ongoing' : 'published');

        if ($target === $schedule->status) {
            return $schedule;
        }

        return DB::transaction(function () use ($schedule, $target) {
            $locked = AuctionSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status, ['published', 'ongoing'], true)) {
                return $locked;
            }

            $now = now('UTC');
            $start = $this->scheduleStart($locked);
            $end = $this->scheduleEnd($locked);
            $resolved = $now->greaterThanOrEqualTo($end)
                ? 'completed'
                : ($now->greaterThanOrEqualTo($start) ? 'ongoing' : 'published');

            if ($resolved !== $locked->status) {
                $locked->update(['status' => $resolved]);
                if ($resolved === 'completed') {
                    $locked->registrations()
                        ->where('status', 'registered')
                        ->update([
                            'status' => 'completed',
                            'active_livestock_id' => null,
                            'updated_at' => now(),
                        ]);
                }
            }

            return $locked;
        });
    }

    public function syncAutomaticStatuses(?string $municipalityId = null): int
    {
        $updated = 0;
        AuctionSchedule::query()
            ->whereIn('status', ['published', 'ongoing'])
            ->when($municipalityId, fn ($query) => $query->where('municipality_id', $municipalityId))
            ->orderBy('auction_date')
            ->chunkById(100, function ($schedules) use (&$updated) {
                foreach ($schedules as $schedule) {
                    $before = $schedule->status;
                    $after = $this->syncScheduleLifecycle($schedule);
                    if ($before !== $after->status) $updated++;
                }
            });

        return $updated;
    }

    public function encoderIndex(User $encoder)
    {
        $municipalityId = $this->encoderMunicipality($encoder);
        $this->syncAutomaticStatuses($municipalityId);

        $assignedHouseIds = DB::table('auction_house_encoder_assignments')
            ->where('user_id', $encoder->id)
            ->where('is_active', true)
            ->select('auction_house_id');

        return AuctionSchedule::query()
            ->where('municipality_id', $municipalityId)
            ->whereIn('auction_house_id', $assignedHouseIds)
            ->with(['auctionHouse', 'barangay', 'municipality'])
            ->withCount(['registrations as registrations_count' => fn ($query) => $query->where('status', 'registered')])
            ->orderByDesc('auction_date')
            ->orderByDesc('start_time')
            ->paginate(20);
    }

    public function create(User $encoder, array $data): AuctionSchedule
    {
        $municipalityId = $this->encoderMunicipality($encoder);
        $this->validateScheduleWindow($data);
        $status = $data['status'] ?? 'draft';
        if ($status === 'published' && $this->manilaDateTime($data['registration_closes_at'])->lessThanOrEqualTo(now('Asia/Manila'))) {
            throw ValidationException::withMessages(['registration_closes_at' => 'A schedule cannot be published after its registration deadline has passed.']);
        }

        $schedule = DB::transaction(function () use ($encoder, $municipalityId, $data, $status) {
            // Lock the venue row so simultaneous schedule submissions cannot reserve overlapping time slots.
            AuctionHouse::query()->whereKey($data['auction_house_id'])->lockForUpdate()->firstOrFail();
            $house = $this->resolveVenue($municipalityId, $data['auction_house_id'], $encoder);
            $this->assertNoVenueOverlap((string) $house->id, $data);

            $schedule = AuctionSchedule::create([
                'municipality_id' => $municipalityId,
                'auction_house_id' => $house->id,
                'barangay_id' => $house->barangay_id,
                'created_by' => $encoder->id,
                'title' => $data['title'],
                'auction_date' => $data['auction_date'],
                'start_time' => $data['start_time'],
                'expected_end_time' => $data['expected_end_time'],
                'venue' => $house->name,
                'registration_opens_at' => $this->utcDateTime($data['registration_opens_at']),
                'registration_closes_at' => $this->utcDateTime($data['registration_closes_at']),
                'description' => $data['description'] ?? null,
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
            ]);

            $this->auditLogs->record($encoder, 'auction_schedule.created', $schedule, $municipalityId, [], [
                'title' => $schedule->title,
                'auction_date' => $schedule->auction_date?->toDateString(),
                'status' => $schedule->status,
            ]);

            return $schedule;
        });

        if ($schedule->status === 'published') {
            $this->notifyVerifiedFarmers($schedule);
        }

        return $schedule->load(['municipality', 'auctionHouse', 'barangay', 'creator'])->loadCount(['registrations as registrations_count' => fn ($query) => $query->where('status', 'registered')]);
    }

    public function updateSchedule(User $encoder, AuctionSchedule $schedule, array $data): AuctionSchedule
    {
        $municipalityId = $this->encoderMunicipality($encoder);
        abort_unless((string) $schedule->municipality_id === $municipalityId, 404);
        $this->assertEncoderAssignedHouse($encoder, (string) $schedule->auction_house_id);

        $schedule = $this->syncScheduleLifecycle($schedule);
        if (!in_array($schedule->status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages([
                'schedule' => 'Only draft or published auction schedules can be edited. Ongoing, completed, and cancelled schedules are locked.',
            ]);
        }

        $merged = array_merge($schedule->only([
            'title', 'auction_date', 'start_time', 'expected_end_time', 'venue',
            'auction_house_id', 'barangay_id', 'registration_opens_at', 'registration_closes_at', 'description',
        ]), $data);
        $this->validateScheduleWindow($merged);

        DB::transaction(function () use ($encoder, $schedule, $data, $merged, $municipalityId) {
            $lockedSchedule = AuctionSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            if (!in_array($lockedSchedule->status, ['draft', 'published'], true)) {
                throw ValidationException::withMessages(['schedule' => 'Only draft or published schedules can be edited.']);
            }

            AuctionHouse::query()->whereKey($merged['auction_house_id'])->lockForUpdate()->firstOrFail();
            $house = $this->resolveVenue($municipalityId, (string) $merged['auction_house_id'], $encoder);
            $this->assertNoVenueOverlap((string) $house->id, $merged, (string) $lockedSchedule->id);

            $updates = $data;
            if (array_key_exists('registration_opens_at', $updates)) $updates['registration_opens_at'] = $this->utcDateTime($updates['registration_opens_at']);
            if (array_key_exists('registration_closes_at', $updates)) $updates['registration_closes_at'] = $this->utcDateTime($updates['registration_closes_at']);
            $updates = array_merge($updates, [
                'auction_house_id' => $house->id,
                'venue' => $house->name,
                'barangay_id' => $house->barangay_id,
            ]);
            $old = $lockedSchedule->only(['title', 'auction_date', 'start_time', 'expected_end_time', 'venue', 'status']);
            $lockedSchedule->update($updates);
            $this->auditLogs->record($encoder, 'auction_schedule.updated', $lockedSchedule, $municipalityId, $old, $lockedSchedule->only(['title', 'auction_date', 'start_time', 'expected_end_time', 'venue', 'status']));
        });

        return $schedule->fresh()->load(['municipality', 'auctionHouse', 'barangay', 'creator'])->loadCount(['registrations as registrations_count' => fn ($query) => $query->where('status', 'registered')]);
    }

    public function changeStatus(User $encoder, AuctionSchedule $schedule, string $newStatus): AuctionSchedule
    {
        $municipalityId = $this->encoderMunicipality($encoder);
        abort_unless((string) $schedule->municipality_id === $municipalityId, 404);
        $this->assertEncoderAssignedHouse($encoder, (string) $schedule->auction_house_id);
        $schedule = $this->syncScheduleLifecycle($schedule);

        $allowed = [
            'draft' => ['published', 'cancelled'],
            'published' => ['cancelled'],
            'ongoing' => ['cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        if (!in_array($newStatus, $allowed[$schedule->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "The schedule cannot move from {$schedule->status} to {$newStatus}.",
            ]);
        }
        if ($newStatus === 'published' && $schedule->registration_closes_at->lessThanOrEqualTo(now('UTC'))) {
            throw ValidationException::withMessages(['status' => 'This schedule cannot be published because its registration deadline has passed.']);
        }

        DB::transaction(function () use ($encoder, $schedule, $newStatus, $municipalityId) {
            $oldStatus = $schedule->status;
            $schedule->update([
                'status' => $newStatus,
                'published_at' => $newStatus === 'published' ? now() : $schedule->published_at,
            ]);

            if (in_array($newStatus, ['completed', 'cancelled'], true)) {
                $registrationStatus = $newStatus === 'completed' ? 'completed' : 'cancelled';
                $schedule->registrations()
                    ->where('status', 'registered')
                    ->update([
                        'status' => $registrationStatus,
                        'active_livestock_id' => null,
                        'updated_at' => now(),
                    ]);
            }

            $this->auditLogs->record($encoder, 'auction_schedule.status_changed', $schedule, $municipalityId, ['status' => $oldStatus], ['status' => $newStatus]);
        });

        if ($newStatus === 'published') {
            $this->notifyVerifiedFarmers($schedule);
        }

        return $schedule->fresh()->load(['municipality', 'auctionHouse', 'barangay'])->loadCount(['registrations as registrations_count' => fn ($query) => $query->where('status', 'registered')]);
    }

    public function encoderShow(User $encoder, AuctionSchedule $schedule): AuctionSchedule
    {
        $municipalityId = $this->encoderMunicipality($encoder);
        abort_unless((string) $schedule->municipality_id === $municipalityId, 404);
        $this->assertEncoderAssignedHouse($encoder, (string) $schedule->auction_house_id);
        $schedule = $this->syncScheduleLifecycle($schedule);

        return $schedule->load([
            'municipality', 'auctionHouse', 'barangay', 'creator',
        ]);
    }

    public function encoderRegistrations(User $encoder, AuctionSchedule $schedule)
    {
        $municipalityId = $this->encoderMunicipality($encoder);
        abort_unless((string) $schedule->municipality_id === $municipalityId, 404);
        $this->assertEncoderAssignedHouse($encoder, (string) $schedule->auction_house_id);
        $schedule = $this->syncScheduleLifecycle($schedule);

        return [
            'schedule' => $schedule->load(['municipality', 'auctionHouse', 'barangay', 'creator']),
            'status_counts' => $schedule->registrations()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'registrations' => $schedule->registrations()
                ->with(['farmer.user', 'livestock.species', 'livestock.breed', 'registeredBy'])
                ->orderByDesc('registered_at')
                ->paginate(25),
        ];
    }

    public function farmerIndex(User $user)
    {
        $farmer = $this->farmerProfile($user, false);
        $this->syncAutomaticStatuses((string) $farmer->municipality_id);

        return AuctionSchedule::query()
            ->where('municipality_id', $farmer->municipality_id)
            ->where('status', 'published')
            ->whereDate('auction_date', '>=', now('Asia/Manila')->toDateString())
            ->where('registration_closes_at', '>', now('UTC'))
            ->with(['municipality', 'auctionHouse', 'barangay'])
            ->withCount(['registrations as registrations_count' => fn ($query) => $query->where('status', 'registered')])
            ->orderBy('auction_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (AuctionSchedule $schedule) => $this->appendScheduleFlags($schedule))
            ->sortBy(fn (array $schedule) => $schedule['registration_open'] ? 0 : 1)
            ->values();
    }

    public function farmerShow(User $user, AuctionSchedule $schedule): array
    {
        $farmer = $this->farmerProfile($user, false);
        abort_unless((string) $schedule->municipality_id === (string) $farmer->municipality_id, 404);
        $schedule = $this->syncScheduleLifecycle($schedule);
        abort_unless(in_array($schedule->status, ['published', 'ongoing', 'completed', 'cancelled'], true), 404);
        $schedule->load(['municipality', 'auctionHouse', 'barangay'])->loadCount(['registrations as registrations_count' => fn ($query) => $query->where('status', 'registered')]);
        $isVerified = $user->verification_status === 'verified';
        $registrationOpen = $schedule->registrationIsOpen();

        return [
            'schedule' => $this->appendScheduleFlags($schedule),
            'can_register' => $isVerified && $registrationOpen,
            'registration_open' => $registrationOpen,
            'farmer_verified' => $isVerified,
        ];
    }

    public function farmerEligibleLivestock(User $user, AuctionSchedule $schedule): array
    {
        $farmer = $this->farmerProfile($user, false);
        abort_unless((string) $schedule->municipality_id === (string) $farmer->municipality_id, 404);
        $schedule = $this->syncScheduleLifecycle($schedule);
        abort_unless($schedule->registrationIsOpen(), 403, 'Registration for this auction is not currently open.');
        abort_unless($user->verification_status === 'verified', 403, 'Your Farmer account must be verified before registering livestock.');

        $records = LivestockRecord::query()
            ->where('farmer_id', $farmer->id)
            ->with(['species', 'breed', 'latestWeightEstimation', 'latestValuation'])
            ->orderByDesc('created_at')
            ->get();

        $activeIds = AuctionRegistration::query()
            ->whereNotNull('active_livestock_id')
            ->whereIn('livestock_id', $records->pluck('id'))
            ->pluck('livestock_id')
            ->map(fn ($id) => (string) $id)
            ->all();
        $activeSet = array_fill_keys($activeIds, true);
        $eligible = [];

        foreach ($records as $livestock) {
            $isEligible = (string) $livestock->municipality_id === (string) $schedule->municipality_id
                && $livestock->status === 'available'
                && $this->livestockRecordIsComplete($livestock)
                && !isset($activeSet[(string) $livestock->id]);

            if ($isEligible) {
                $eligible[] = $this->livestockPayload($livestock, true, null);
            }
        }

        return $eligible;
    }

    public function registerLivestock(User $user, AuctionSchedule $schedule, array $livestockIds): EloquentCollection
    {
        $farmer = $this->farmerProfile($user, true);
        $ids = array_values(array_unique($livestockIds));
        if (count($ids) !== count($livestockIds)) {
            throw ValidationException::withMessages(['livestock_ids' => 'Duplicate livestock selections are not allowed.']);
        }
        sort($ids, SORT_STRING); // Consistent lock order reduces concurrent registration deadlocks.

        return DB::transaction(function () use ($user, $farmer, $schedule, $ids) {
            $lockedSchedule = AuctionSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            abort_unless((string) $lockedSchedule->municipality_id === (string) $farmer->municipality_id, 404);
            $lockedSchedule = $this->syncScheduleLifecycle($lockedSchedule);

            if (!$lockedSchedule->registrationIsOpen()) {
                throw ValidationException::withMessages(['schedule' => 'Registration for this auction is not currently open.']);
            }

            $records = LivestockRecord::query()
                ->whereIn('id', $ids)
                ->where('farmer_id', $farmer->id)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (LivestockRecord $record) => (string) $record->id);

            if ($records->count() !== count($ids)) {
                throw ValidationException::withMessages(['livestock_ids' => 'One or more selected livestock records do not belong to your account.']);
            }

            $created = new EloquentCollection();
            foreach ($ids as $id) {
                /** @var LivestockRecord $livestock */
                $livestock = $records->get((string) $id);
                if ((string) $livestock->municipality_id !== (string) $lockedSchedule->municipality_id) {
                    throw ValidationException::withMessages(['livestock_ids' => 'Selected livestock must belong to the auction municipality.']);
                }
                if ($livestock->status !== 'available') {
                    throw ValidationException::withMessages(['livestock_ids' => 'Only available livestock can be registered.']);
                }
                if (!$this->livestockRecordIsComplete($livestock)) {
                    throw ValidationException::withMessages(['livestock_ids' => 'Complete the required identification, sale purpose, and condition details before registering this livestock.']);
                }
                if (AuctionRegistration::query()->where('active_livestock_id', $livestock->id)->exists()) {
                    throw ValidationException::withMessages(['livestock_ids' => 'One or more selected animals already have an active auction registration. Each animal can only be registered in one active auction at a time.']);
                }

                $registration = AuctionRegistration::create([
                    'auction_schedule_id' => $lockedSchedule->id,
                    'livestock_id' => $livestock->id,
                    'farmer_id' => $farmer->id,
                    'registered_by' => $user->id,
                    'active_livestock_id' => $livestock->id,
                    'status' => 'registered',
                    'registered_at' => now(),
                ]);

                $this->auditLogs->record($user, 'auction_livestock.registered', $registration, $lockedSchedule->municipality_id, [], [
                    'auction_schedule_id' => $lockedSchedule->id,
                    'livestock_id' => $livestock->id,
                    'status' => 'registered',
                ]);
                $created->push($registration);
            }

            return $created->load(['schedule', 'livestock.species', 'livestock.breed']);
        });
    }

    public function farmerRegistrations(User $user)
    {
        $farmer = $this->farmerProfile($user, false);

        return AuctionRegistration::query()
            ->where('farmer_id', $farmer->id)
            ->with([
                'schedule.municipality', 'schedule.barangay',
                'livestock.species', 'livestock.breed',
            ])
            ->latest('registered_at')
            ->paginate(20);
    }

    public function withdraw(User $user, AuctionRegistration $registration): AuctionRegistration
    {
        $farmer = $this->farmerProfile($user, true);
        abort_unless((string) $registration->farmer_id === (string) $farmer->id, 404);

        return DB::transaction(function () use ($user, $farmer, $registration) {
            $locked = AuctionRegistration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();
            $schedule = AuctionSchedule::query()->whereKey($locked->auction_schedule_id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'registered' || $schedule->status !== 'published' || now()->greaterThan($schedule->registration_closes_at)) {
                throw ValidationException::withMessages(['registration' => 'This registration can no longer be withdrawn.']);
            }

            $locked->update([
                'status' => 'withdrawn',
                'active_livestock_id' => null,
                'withdrawn_at' => now(),
            ]);
            $this->auditLogs->record($user, 'auction_livestock.withdrawn', $locked, $schedule->municipality_id, ['status' => 'registered'], ['status' => 'withdrawn']);

            return $locked->fresh()->load(['schedule', 'livestock.species', 'livestock.breed']);
        });
    }

    private function livestockRecordIsComplete(LivestockRecord $livestock): bool
    {
        // Breed, age, weight, photo, and market valuation are intentionally optional.
        return filled($livestock->species_id)
            && in_array($livestock->sex, ['male', 'female', 'unknown'], true)
            && in_array($livestock->sale_purpose, ['slaughter', 'breeding', 'fattening', 'work'], true)
            && $livestock->condition_score !== null
            && (int) $livestock->condition_score >= 1
            && (int) $livestock->condition_score <= 5;
    }

    private function livestockPayload(LivestockRecord $livestock, bool $eligible, ?string $reason): array
    {
        $actual = $livestock->actual_weight_kg;
        $estimated = $livestock->latestWeightEstimation?->estimated_weight_kg;
        $weight = $actual !== null ? $actual : $estimated;
        $weightType = $actual !== null ? 'Actual Weight' : ($estimated !== null ? 'Estimated Weight' : 'Not Recorded');

        return [
            'id' => $livestock->id,
            'species' => $livestock->species?->name ?? 'Unknown Species',
            'breed' => $livestock->breed?->name ?? 'Unspecified Breed',
            'sex' => ucfirst((string) ($livestock->sex ?? 'unknown')),
            'age_months' => $livestock->age_months,
            'sale_purpose' => $livestock->sale_purpose,
            'condition_score' => $livestock->condition_score,
            'status' => $livestock->status,
            'weight_kg' => $weight,
            'weight_type' => $weightType,
            'estimated_value' => $livestock->latestValuation?->estimated_value,
            'photo_url' => $livestock->photo_path ? asset('storage/'.$livestock->photo_path) : null,
            'eligible' => $eligible,
            'ineligible_reason' => $reason,
        ];
    }

    private function appendScheduleFlags(AuctionSchedule $schedule): array
    {
        $schedule->loadMissing(['municipality', 'barangay']);
        return array_merge($schedule->toArray(), [
            'registration_open' => $schedule->registrationIsOpen(),
            'registration_has_not_opened' => now('UTC')->lessThan($schedule->registration_opens_at),
            'registration_closed' => now('UTC')->greaterThan($schedule->registration_closes_at),
        ]);
    }

    private function notifyVerifiedFarmers(AuctionSchedule $schedule): void
    {
        $recipients = User::query()
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->whereHas('roles', fn ($query) => $query->where('slug', 'farmer'))
            ->whereHas('farmerProfile', fn ($query) => $query->where('municipality_id', $schedule->municipality_id))
            ->with('farmerProfile')
            ->get();

        $date = $schedule->auction_date->format('M d, Y');
        $time = Carbon::parse($schedule->start_time)->format('g:i A');
        foreach ($recipients as $recipient) {
            $recipient->notify(new SystemAlertNotification(
                'auction.schedule_published',
                'Bag-ong Auction Schedule!',
                "A new livestock auction is scheduled: {$schedule->title} on {$date} at {$time}, {$schedule->venue}. Register your eligible livestock before the registration deadline.",
                (string) $schedule->municipality_id,
                'auction_schedule',
                (string) $schedule->id,
                false,
                '/auctions/'.$schedule->id,
            ));
        }
    }
}
