<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tells admins when an uploaded update needs database changes, so they can
 * apply them from Settings without SSH.
 */
class DatabaseStatus
{
    private const CACHE_KEY = 'ycc.pending_migrations';

    /** @var array<string, bool> */
    private static array $columns = [];

    /** @return list<string> names of migrations that haven't run yet */
    public static function pending(): array
    {
        // The key includes how many migration files exist, so uploading an update
        // with a new migration is noticed straight away instead of after the cache expires.
        $files = count(glob(database_path('migrations/*.php')) ?: []);

        return Cache::remember(self::CACHE_KEY.'.'.$files, 300, function () {
            try {
                $migrator = app('migrator');
                if (! $migrator->repositoryExists()) {
                    return [];
                }
                $files = $migrator->getMigrationFiles([database_path('migrations')]);
                $ran = $migrator->getRepository()->getRan();

                return array_values(array_diff(array_keys($files), $ran));
            } catch (Throwable) {
                return [];
            }
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY.'.'.count(glob(database_path('migrations/*.php')) ?: []));
        self::$columns = [];
    }

    /** Whether a column exists yet; remembered for the rest of the request. */
    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        try {
            return self::$columns[$key] ??= Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /** Whether a table exists yet; remembered for the rest of the request. */
    public static function hasTable(string $table): bool
    {
        $key = $table.'.*';

        try {
            return self::$columns[$key] ??= Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /** Feedback and volunteer hours arrived together in one update. */
    public static function feedbackReady(): bool
    {
        return self::hasTable('program_feedback') && self::hasColumn('programs', 'volunteer_hours');
    }
}
