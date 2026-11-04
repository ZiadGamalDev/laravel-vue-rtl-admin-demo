<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * A tiny self-hosted "issue tracker" for exceptions.
 *
 * Every reported exception is grouped by a fingerprint (class + file + line),
 * so repeats bump a counter instead of adding rows. A resolved issue that
 * happens again is reopened.
 */
class ErrorRecorder
{
    private const MAX_FRAMES = 30;

    private static bool $recording = false;

    public function record(Throwable $e): void
    {
        // Never let error tracking cause a new error (or loop on its own failures).
        if (self::$recording) {
            return;
        }
        self::$recording = true;

        try {
            $this->store($e);
        } catch (Throwable) {
            // The database may be unavailable or not migrated yet; the normal log still has the error.
        } finally {
            self::$recording = false;
        }
    }

    public static function fingerprint(Throwable $e): string
    {
        return sha1(get_class($e).'|'.self::relative($e->getFile()).'|'.$e->getLine());
    }

    private function store(Throwable $e): void
    {
        $now = now();
        $fingerprint = self::fingerprint($e);
        // Only attach request details when the error happened while handling a routed HTTP request.
        $request = app()->bound('request') && request()->route() ? request() : null;

        $attributes = [
            'exception_class' => get_class($e),
            'message' => Str::limit($e->getMessage() !== '' ? $e->getMessage() : get_class($e), 2000),
            'file' => self::relative($e->getFile()),
            'line' => $e->getLine(),
            'trace' => json_encode($this->frames($e), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'last_seen_at' => $now,
            'resolved_at' => null,
            'last_method' => $request?->method(),
            // Path only: query strings carry search terms, names and tokens (personal data).
            'last_url' => $request ? Str::limit('/'.ltrim($request->path(), '/'), 490) : null,
            'updated_at' => $now,
        ];

        $updated = DB::table('error_events')
            ->where('fingerprint', $fingerprint)
            ->update([...$attributes, 'count' => DB::raw('count + 1')]);

        if ($updated === 0) {
            $inserted = DB::table('error_events')->insertOrIgnore([
                ...$attributes,
                'fingerprint' => $fingerprint,
                'count' => 1,
                'first_seen_at' => $now,
                'created_at' => $now,
            ]);

            if ($inserted === 0) {
                DB::table('error_events')
                    ->where('fingerprint', $fingerprint)
                    ->update([...$attributes, 'count' => DB::raw('count + 1')]);
            }
        }
    }

    /** @return list<array{file: ?string, line: ?int, function: string}> */
    private function frames(Throwable $e): array
    {
        $frames = [['file' => self::relative($e->getFile()), 'line' => $e->getLine(), 'function' => get_class($e)]];

        foreach (array_slice($e->getTrace(), 0, self::MAX_FRAMES - 1) as $frame) {
            $frames[] = [
                'file' => isset($frame['file']) ? self::relative($frame['file']) : null,
                'line' => $frame['line'] ?? null,
                'function' => isset($frame['class']) ? $frame['class'].($frame['type'] ?? '::').$frame['function'] : ($frame['function'] ?? '{closure}'),
            ];
        }

        return $frames;
    }

    /** Paths relative to the project root, so no server layout leaks into the UI. */
    private static function relative(string $path): string
    {
        return ltrim(Str::after($path, base_path()), DIRECTORY_SEPARATOR);
    }
}
