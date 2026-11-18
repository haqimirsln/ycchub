<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One MT position held in one session, e.g. Setiausaha for 2026/2027. */
#[Fillable(['session', 'position', 'user_id', 'holder_name', 'holder_matric', 'sort'])]
class MtTerm extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return config("ycc.positions.{$this->position}.label", ucwords(str_replace('_', ' ', $this->position)));
    }

    public function contact(): ?string
    {
        return config("ycc.positions.{$this->position}.contact");
    }

    public function team(): ?string
    {
        return config("ycc.positions.{$this->position}.team");
    }

    /** Name in tidy case for display: "Hazirah binti Mohd Azhar", whatever caps it was typed in. */
    public function displayName(): string
    {
        $small = ['bin', 'binti', 'bt', 'bte', 'a/l', 'a/p', 'al', 'ap', 'van', 'de'];

        return collect(preg_split('/\s+/', trim($this->name())))
            ->map(fn ($w) => in_array(mb_strtolower($w), $small, true) ? mb_strtolower($w) : mb_convert_case(mb_strtolower($w), MB_CASE_TITLE))
            ->implode(' ');
    }

    /** The account's current name, or the name from the list when they haven't signed up yet. */
    public function name(): string
    {
        return $this->user?->full_name ?? $this->holder_name ?? 'To be announced';
    }
}
