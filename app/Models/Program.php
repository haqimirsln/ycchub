<?php

namespace App\Models;

use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'title', 'description', 'category', 'venue', 'starts_at', 'ends_at',
    'registration_closes_at', 'max_capacity', 'poster_path', 'status',
    'counts_for_activity', 'location_type', 'latitude', 'longitude',
    'meeting_url', 'meeting_info', 'volunteer_hours', 'committee_count',
])]
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'max_capacity' => 'integer',
            'counts_for_activity' => 'boolean',
            'checkin_open' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'volunteer_hours' => 'float',
            'committee_count' => 'integer',
            'announced_at' => 'datetime',
            'pushed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Program $program) {
            if (empty($program->checkin_code)) {
                $program->checkin_code = Str::lower(Str::random(24));
            }
        });
    }

    // ---- Relationships ---------------------------------------------------

    public function registrations(): HasMany
    {
        return $this->hasMany(ProgramRegistration::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---- Scopes ----------------------------------------------------------

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at');
    }

    public function scopePast(Builder $q): Builder
    {
        return $q->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at');
    }

    /** Adds registered_count (active seats) and attended_count columns. */
    public function scopeWithSeatCounts(Builder $q): Builder
    {
        return $q->withCount([
            'registrations as seats_taken' => fn ($r) => $r->whereIn('status', ['registered', 'attended', 'absent']),
            'registrations as attended_count' => fn ($r) => $r->where('status', 'attended'),
        ]);
    }

    // ---- State helpers ---------------------------------------------------

    public function categoryLabel(): string
    {
        return config('ycc.program_categories.'.$this->category, 'Other');
    }

    public function endsAtOrDefault(): Carbon
    {
        return $this->ends_at ?? $this->starts_at->copy()->endOfDay();
    }

    public function isPast(): bool
    {
        return $this->endsAtOrDefault()->isPast();
    }

    public function isToday(): bool
    {
        return $this->starts_at->isToday();
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->isPast();
    }

    public function registrationCloseTime(): Carbon
    {
        return $this->registration_closes_at ?? $this->starts_at;
    }

    public function seatsTaken(): int
    {
        if (array_key_exists('seats_taken', $this->attributes)) {
            return (int) $this->attributes['seats_taken'];
        }

        return $this->registrations()->whereIn('status', ['registered', 'attended', 'absent'])->count();
    }

    public function seatsLeft(): ?int
    {
        if ($this->max_capacity === null) {
            return null;
        }

        return max(0, $this->max_capacity - $this->seatsTaken());
    }

    public function isFull(): bool
    {
        return $this->max_capacity !== null && $this->seatsLeft() <= 0;
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === 'published' && $this->registrationCloseTime()->isFuture();
    }

    /** Excos can mark attendance from the start day until the lock window ends. */
    public function attendanceEditable(): bool
    {
        if ($this->status === 'cancelled') {
            return false;
        }
        $opens = $this->starts_at->copy()->startOfDay();
        $locks = $this->starts_at->copy()->addDays((int) config('ycc.attendance_lock_days', 14))->endOfDay();

        return now()->between($opens, $locks);
    }

    public function capacityPercent(): int
    {
        if (! $this->max_capacity) {
            return 0;
        }

        return (int) min(100, round($this->seatsTaken() / $this->max_capacity * 100));
    }

    public function committee(): HasMany
    {
        return $this->hasMany(ProgramCommittee::class)->orderBy('sort');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(ProgramFeedback::class);
    }

    /** End time for calendars: the set end, or two hours after the start. */
    public function calendarEndsAt(): Carbon
    {
        return ($this->ends_at ?? $this->starts_at->copy()->addHours(2))->copy();
    }

    /** Location line for calendars: the venue, or where to find the online link. */
    public function calendarLocation(): string
    {
        return match ($this->locationType()) {
            'online' => 'Online on '.$this->meetingPlatform().' (join link in YCC Hub)',
            'hybrid' => $this->venue.' (or online on '.$this->meetingPlatform().')',
            default => (string) $this->venue,
        };
    }

    public function calendarDetails(): string
    {
        $text = Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->description)), 600);

        return $text."\n\nDetails and updates: ".route('programs.show', $this);
    }

    public function googleCalendarUrl(): string
    {
        $fmt = fn (Carbon $t) => $t->copy()->utc()->format('Ymd\THis\Z');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $this->title.' (YCC)',
            'dates' => $fmt($this->starts_at).'/'.$fmt($this->calendarEndsAt()),
            'details' => $this->calendarDetails(),
            'location' => $this->calendarLocation(),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Hours a member gives by attending: the exco's figure if set (for multi-day
     * trips), otherwise start to end time. No end time means no hours.
     */
    public function volunteerHours(): float
    {
        if ($this->volunteer_hours !== null) {
            return (float) $this->volunteer_hours;
        }
        if (! $this->ends_at || $this->ends_at->lessThanOrEqualTo($this->starts_at)) {
            return 0.0;
        }

        return round($this->starts_at->diffInMinutes($this->ends_at) / 60, 1);
    }

    /** Feedback opens once the program has started and stays open for 30 days. */
    public function isFeedbackOpen(): bool
    {
        return $this->status === 'published'
            && $this->starts_at->isPast()
            && $this->starts_at->greaterThan(now()->subDays(30));
    }

    // ---- Where it happens ---------------------------------------------------

    public const LOCATION_TYPES = [
        'in_person' => 'In person',
        'online' => 'Online',
        'hybrid' => 'In person + online',
    ];

    public function locationType(): string
    {
        return array_key_exists((string) $this->location_type, self::LOCATION_TYPES) ? $this->location_type : 'in_person';
    }

    public function isOnline(): bool
    {
        return $this->locationType() === 'online';
    }

    /** Has a physical place people go to. */
    public function hasPlace(): bool
    {
        return $this->locationType() !== 'online';
    }

    /** Has an online session people join. */
    public function hasMeeting(): bool
    {
        return $this->locationType() !== 'in_person';
    }

    public function hasPin(): bool
    {
        return $this->hasPlace() && $this->latitude !== null && $this->longitude !== null;
    }

    /** Short line for cards and lists, e.g. "Setiu Wetlands" or "Online on Webex". */
    public function whereLabel(): string
    {
        return match ($this->locationType()) {
            'online' => 'Online on '.$this->meetingPlatform(),
            'hybrid' => $this->venue.' + online',
            default => (string) $this->venue,
        };
    }

    /** Google Maps: the exact pin when we have one, otherwise a search for the venue text. */
    public function mapUrl(): ?string
    {
        if (! $this->hasPlace()) {
            return null;
        }
        $query = $this->hasPin() ? $this->latitude.','.$this->longitude : $this->venue;

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode((string) $query);
    }

    /** Google Maps embed (no API key needed) for the program page. */
    public function mapEmbedUrl(): ?string
    {
        if (! $this->hasPlace() || blank($this->venue)) {
            return null;
        }
        $query = $this->hasPin() ? $this->latitude.','.$this->longitude : $this->venue;

        return 'https://maps.google.com/maps?q='.rawurlencode((string) $query).'&z='.($this->hasPin() ? 17 : 15).'&output=embed';
    }

    public function directionsUrl(): ?string
    {
        if (! $this->hasPlace()) {
            return null;
        }
        $to = $this->hasPin() ? $this->latitude.','.$this->longitude : $this->venue;

        return 'https://www.google.com/maps/dir/?api=1&destination='.urlencode((string) $to);
    }

    public function meetingPlatform(): string
    {
        $host = strtolower((string) parse_url((string) $this->meeting_url, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'webex') => 'Webex',
            str_contains($host, 'zoom.') => 'Zoom',
            str_contains($host, 'meet.google') => 'Google Meet',
            str_contains($host, 'teams.') => 'Microsoft Teams',
            $host === '' && $this->isOnline() => config('ycc.default_meeting_platform', 'Webex'),
            default => 'the meeting',
        };
    }

    /** The join button shows from 30 minutes before the start until 30 minutes after the end. */
    public function joinOpensAt(): Carbon
    {
        return $this->starts_at->copy()->subMinutes(30);
    }

    public function isJoinOpen(): bool
    {
        $end = ($this->ends_at ?? $this->starts_at->copy()->addHours(4))->copy()->addMinutes(30);

        return now()->between($this->joinOpensAt(), $end);
    }

    public function isJoinOver(): bool
    {
        return now()->greaterThan(($this->ends_at ?? $this->starts_at->copy()->addHours(4))->copy()->addMinutes(30));
    }

    public function posterUrl(): ?string
    {
        return $this->poster_path ? asset('uploads/'.$this->poster_path) : null;
    }

    /** Where the small list-size copy of the poster lives (posters/thumbs/...). */
    public function posterThumbPath(): ?string
    {
        if (! $this->poster_path) {
            return null;
        }

        return 'posters/thumbs/'.preg_replace('/\.[a-z0-9]+$/i', '', Str::after($this->poster_path, 'posters/')).'.jpg';
    }

    /**
     * Small poster for cards and lists, so a page of programs doesn't download
     * several full-size posters. Older posters get their thumbnail made the
     * first time they're shown; if that isn't possible we fall back to the original.
     */
    public function posterThumbUrl(): ?string
    {
        if (! $this->poster_path) {
            return null;
        }
        $thumb = $this->posterThumbPath();
        if (! is_file(public_path('uploads/'.$thumb)) && ! $this->makePosterThumb()) {
            return $this->posterUrl();
        }

        return asset('uploads/'.$thumb);
    }

    /** Writes a 640px-wide JPEG copy of the poster. Returns false if the server can't. */
    public function makePosterThumb(): bool
    {
        $source = $this->poster_path ? public_path('uploads/'.$this->poster_path) : null;
        if (! $source || ! is_file($source) || ! function_exists('imagecreatefromstring')) {
            return false;
        }

        try {
            $src = @imagecreatefromstring((string) file_get_contents($source));
            if (! $src) {
                return false;
            }
            $w = imagesx($src);
            $h = imagesy($src);
            $tw = min(640, $w);
            $th = (int) round($h * $tw / $w);
            $dst = imagecreatetruecolor($tw, $th);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
            ob_start();
            imagejpeg($dst, null, 80);

            return Storage::disk('uploads')->put($this->posterThumbPath(), (string) ob_get_clean());
        } catch (\Throwable) {
            return false;
        }
    }

    /** Removes the poster and its thumbnail from disk. */
    public function deletePosterFiles(): void
    {
        if ($this->poster_path) {
            Storage::disk('uploads')->delete([$this->poster_path, $this->posterThumbPath()]);
        }
    }
}
