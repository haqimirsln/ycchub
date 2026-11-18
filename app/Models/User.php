<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'matric_no', 'full_name', 'email', 'phone_number', 'faculty', 'team', 'member_type', 'email_opt_out',
    'programme', 'year_of_study', 'password', 'pdpa_consent_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** member = club member, admin = MT (Majlis Tertinggi), superadmin = system owner, advisor = view-only (Penasihat, staff). */
    public const ROLES = ['member', 'admin', 'superadmin', 'advisor'];

    public const ROLE_LABELS = [
        'member' => 'Member',
        'admin' => 'MT (admin)',
        'superadmin' => 'Superadmin',
        'advisor' => 'Advisor (view-only)',
    ];

    public const MEMBER_TYPES = [
        'student' => 'UMT student',
        'alumni' => 'Alumni',
        'staff' => 'Staff / Advisor',
    ];

    public const ACTIVITY_LABELS = [
        'new' => 'New',
        'active' => 'Active',
        'at_risk' => 'At-Risk',
        'inactive' => 'Inactive',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'reviewed_at' => 'datetime',
            'pdpa_consent_at' => 'datetime',
            'last_attended_on' => 'date',
            'activity_checked_at' => 'datetime',
            'year_of_study' => 'integer',
            'total_attended' => 'integer',
            'email_opt_out' => 'boolean',
        ];
    }

    // ---- Relationships ---------------------------------------------------

    public function registrations(): HasMany
    {
        return $this->hasMany(ProgramRegistration::class);
    }

    public function meritEntries(): HasMany
    {
        return $this->hasMany(MeritEntry::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ---- Scopes ----------------------------------------------------------

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('account_status', 'approved');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('account_status', 'pending');
    }

    /** Approved club members and MT the activity engine tracks (the superadmin runs the system, so isn't). */
    public function scopeTracked(Builder $q): Builder
    {
        return $q->approved()->whereIn('role', ['member', 'admin']);
    }

    // ---- Helpers ---------------------------------------------------------

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /** MT (admin) or superadmin: can run the club side of the system. */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin'], true);
    }

    /** Older name for isAdmin(), kept so existing checks read naturally. */
    public function isExco(): bool
    {
        return $this->isAdmin();
    }

    /** Penasihat or staff: sees the MT side of the system but can't change anything. */
    public function isAdvisor(): bool
    {
        return $this->role === 'advisor';
    }

    /** Members and MT get an activity status; the superadmin and advisors don't. */
    public function hasActivityStatus(): bool
    {
        return in_array($this->role, ['member', 'admin'], true);
    }

    /** Normal members are the ones who earn merit and demerit. */
    public function earnsMerit(): bool
    {
        return $this->role === 'member' && $this->isApproved();
    }

    public function isAlumni(): bool
    {
        return $this->member_type === 'alumni';
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst((string) $this->role);
    }

    /** MT position this session, e.g. "Setiausaha", or null. */
    public function positionLabel(): ?string
    {
        return app(\App\Services\MtService::class)->labelOf($this);
    }

    /** Position if they hold one, otherwise the role, e.g. "Presiden" or "Member". */
    public function titleLabel(): string
    {
        return $this->positionLabel() ?? $this->roleLabel();
    }

    public function teamName(): ?string
    {
        return $this->team ? (config('ycc.teams.'.$this->team) ?? $this->team) : null;
    }

    public function isApproved(): bool
    {
        return $this->account_status === 'approved';
    }

    public function activityLabel(): string
    {
        return self::ACTIVITY_LABELS[$this->activity_status] ?? ucfirst($this->activity_status);
    }

    /** First name for greetings, skipping titles: "Prof. Madya Dr. Mohd Uzair" gives "Mohd". */
    public function firstName(): string
    {
        $titles = ['prof', 'prof.', 'madya', 'dr', 'dr.', 'ts', 'ts.', 'ir', 'ir.', 'en', 'en.', 'encik', 'puan', 'pn', 'pn.', 'cik', 'mr', 'mr.', 'ms', 'ms.', 'mrs', 'mrs.', 'assoc.', 'associate', 'professor'];
        foreach (preg_split('/\s+/', trim((string) $this->full_name)) as $word) {
            if ($word !== '' && ! in_array(mb_strtolower($word), $titles, true)) {
                return $word;
            }
        }

        return (string) $this->full_name;
    }

    /** Where the profile picture lives on the uploads disk (public/uploads). */
    public function avatarPath(): string
    {
        return 'avatars/'.$this->getKey().'.jpg';
    }

    /** Public URL of the profile picture, or null when the member hasn't added one. */
    public function avatarUrl(): ?string
    {
        $file = public_path('uploads/'.$this->avatarPath());
        if (! is_file($file)) {
            return null;
        }

        // The version stamp makes browsers fetch the new photo right after a change.
        return asset('uploads/'.$this->avatarPath()).'?v='.filemtime($file);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => ! in_array(strtolower($p), ['bin', 'binti', 'bt', 'b.', 'a/l', 'a/p'], true)));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }

    /** Faculty as the member typed it (older accounts may still hold a code like FSPA). */
    public function facultyName(): ?string
    {
        return $this->faculty ? (config('ycc.faculties.'.$this->faculty) ?? $this->faculty) : null;
    }

    /** Phone in international format for wa.me links (Malaysian numbers assumed). */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone_number);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '60')) {
            return $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '6'.$digits;
        }

        return '60'.$digits;
    }
}
