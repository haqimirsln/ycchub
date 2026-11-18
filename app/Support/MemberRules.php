<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;

/** Validation shared by sign-up and the profile page. */
class MemberRules
{
    /** @return array<string, array<int, mixed>> */
    public static function studies(bool $teamRequired = true, bool $allowStaff = false): array
    {
        return [
            'member_type' => ['required', Rule::in($allowStaff ? array_keys(User::MEMBER_TYPES) : ['student', 'alumni'])],
            'team' => [$teamRequired ? 'required' : 'nullable', Rule::in(array_keys(config('ycc.teams')))],
            'faculty' => ['nullable', 'string', 'max:120'],
            'programme' => ['nullable', 'string', 'max:150'],
            'year_of_study' => ['nullable', 'integer', 'between:1,7'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'team.required' => 'Pick the team you want to join.',
            'member_type.required' => 'Tell us if you are a UMT student or alumni.',
        ];
    }

    /** Tidies the typed faculty and drops year of study for alumni. */
    public static function clean(array $data): array
    {
        if (isset($data['faculty'])) {
            $data['faculty'] = trim(preg_replace('/\s+/', ' ', (string) $data['faculty'])) ?: null;
        }
        if (in_array($data['member_type'] ?? 'student', ['alumni', 'staff'], true)) {
            $data['year_of_study'] = null;
        }

        return $data;
    }
}
