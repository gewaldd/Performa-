<?php
/**
 * Performa Backend - Shared ML Bridge
 * Location: backend/ml_bridge.php
 *
 * Manuscript alignment (Final Manuscript Sept 2026):
 * - Logic Tier: single entry to RF (predict.py) + Gemini (gemini_recommender.py)
 *   via `ml/gemini_recommender.py --input-stdin`. No classification semantics here.
 * - P.502-504 audit trail: callers log model_version for Article 296 traceability.
 * - P.121 human-in-loop preserved by callers (pending_approval), not overridden here.
 *
 * Operational notes:
 * - Preserves the Apache Winsock env fix (SystemRoot/SystemDrive/PATH) from
 *   Employer/rate_employee.php so service contexts keep working.
 * - Returns the first candidate interpreter that actually imports sklearn
 *   (probed once, then cached for an hour); env PYTHON_EXEC is preferred but
 *   still validated so a stale pin cannot silently select a bare runtime.
 *   Falls back to the first resolvable candidate if none validates.
 */

if (!function_exists('ml_python_has_sklearn')) {
    // Cheap capability probe with per-path file cache (1h TTL) so the steady
    // state cost is one filemtime + one small JSON read per request.
    function ml_python_has_sklearn(string $py): bool
    {
        static $mem = [];
        if (array_key_exists($py, $mem)) {
            return $mem[$py];
        }
        // Keyed by interpreter path + PATH: Store-stub aliases can resolve to
        // different runtimes depending on the parent process environment, so a
        // validation result from one context must never poison another.
        $cacheFile = rtrim(sys_get_temp_dir(), '/\\') . '/performa_python_' . md5($py . "\0" . (string) getenv('PATH')) . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) && array_key_exists('ok', $cached)) {
                $mem[$py] = (bool) $cached['ok'];
                return $mem[$py];
            }
        }
        $ok = false;
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $p = @proc_open('"' . $py . '" -c "import sklearn"', $descriptors, $pipes, sys_get_temp_dir(), ml_build_env());
        if (is_resource($p)) {
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $exit = null;
            $status = ['running' => true];
            $deadline = time() + 5;
            do {
                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;
                if (@stream_select($read, $write, $except, 1) === false) {
                    break;
                }
                foreach ($read as $s) {
                    stream_get_contents($s);
                }
                $status = proc_get_status($p);
                if (!$status['running']) {
                    $exit = $status['exitcode'];
                    break;
                }
            } while (time() < $deadline);
            if (($status['running'] ?? false)) {
                @proc_terminate($p);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($p);
            $ok = ($exit === 0);
        }
        @file_put_contents($cacheFile, json_encode(['ok' => $ok, 'at' => time()]));
        $mem[$py] = $ok;
        return $ok;
    }
}

if (!function_exists('ml_resolve_python_exec')) {
    function ml_resolve_python_exec(): string
    {
        $candidates = [];
        foreach (['PYTHON_EXEC', 'PYTHON_PATH'] as $k) {
            $v = getenv($k);
            if (is_string($v) && $v !== '' && file_exists($v)) {
                $candidates[] = $v;
            }
        }
        $pathBins = (PHP_OS_FAMILY === 'Windows')
            ? ['python3', 'python']
            : ['python3', 'python'];
        foreach ($pathBins as $bin) {
            $cmd = (PHP_OS_FAMILY === 'Windows') ? "where {$bin} 2>NUL" : "command -v {$bin} 2>/dev/null";
            $found = is_string($o = @shell_exec($cmd)) ? trim($o) : '';
            if ($found !== '') {
                $first = trim(strtok($found, "\r\n"));
                $candidates[] = $first !== '' ? $first : $bin;
            }
        }
        // Project-local runtimes / legacy hardcoded paths
        $root = dirname(__DIR__);
        foreach ([
            $root . '/.venv/Scripts/python.exe',
            $root . '/.venv/bin/python',
            $root . '/ml/Python/pythoncore-3.14-64/python.exe',
            'C:\\Python314\\python.exe',
            'C:\\Users\\andrew\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe',
        ] as $c) {
            if (is_string($c) && $c !== '' && file_exists($c)) {
                $candidates[] = $c;
            }
        }
        $candidates[] = (PHP_OS_FAMILY === 'Windows') ? 'py' : 'python3';
        $candidates = array_values(array_unique($candidates));
        foreach ($candidates as $c) {
            if (ml_python_has_sklearn($c)) {
                return $c;
            }
        }
        // Best effort: previous behaviour (first resolvable candidate).
        return $candidates[0];
    }
}

if (!function_exists('ml_build_env')) {
    function ml_build_env(): array
    {
        // proc_open env must be string=>string; $_SERVER contains arrays
        // (argv, etc.) that trigger "Array to string conversion" warnings.
        $flat = [];
        foreach (array_merge($_SERVER ?? [], $_ENV ?? []) as $k => $v) {
            if (is_string($k) && (is_string($v) || is_int($v) || is_float($v) || is_bool($v))) {
                $flat[$k] = (string) $v;
            }
        }
        return array_merge($flat, [
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'SystemDrive' => getenv('SystemDrive') ?: 'C:',
            'windir' => getenv('windir') ?: 'C:\\Windows',
            'PATH' => getenv('PATH') ?: 'C:\\Windows\\system32;C:\\Windows',
            'GEMINI_API_KEY' => getenv('GEMINI_API_KEY') ?: '',
            'PYTHONIOENCODING' => 'utf-8',
        ]);
    }
}

if (!function_exists('ml_canonical_scores')) {
    function ml_canonical_scores(array $scores): array
    {
        return [
            "task_completion" => floatval($scores['task_completion'] ?? 3.0),
            "attendance_punctuality" => floatval($scores['attendance_punctuality'] ?? 3.0),
            "communication_teamwork" => floatval($scores['communication_teamwork'] ?? 3.0),
            "initiative_adaptability" => floatval($scores['initiative_adaptability'] ?? 3.0),
            "quality_of_work" => floatval($scores['quality_of_work'] ?? 3.0),
        ];
    }
}

if (!defined('ML_INVOKE_TIMEOUT_SECS')) {
    // Must stay well under PHP max_execution_time (120s in project php.ini
    // for local `php -S`) so a slow Gemini call degrades to a graceful
    // fallback instead of a fatal HTTP 500. Typical calls take 15-25s.
    define('ML_INVOKE_TIMEOUT_SECS', 60);
}

if (!function_exists('ml_invoke_stdin')) {
    /**
     * @return array{ok:bool, output:string, stderr:string, python:string, exit:?int, timedOut:bool}
     */
    function ml_invoke_stdin(string $scriptPath, array $payload, int $timeoutSecs = ML_INVOKE_TIMEOUT_SECS): array
    {
        $pythonExec = ml_resolve_python_exec();
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];
        $command = "\"{$pythonExec}\" \"{$scriptPath}\" --input-stdin";
        $cwd = dirname($scriptPath);
        $process = proc_open($command, $descriptorspec, $pipes, $cwd, ml_build_env());
        if (!is_resource($process)) {
            return ['ok' => false, 'output' => '', 'stderr' => '', 'python' => $pythonExec, 'exit' => null, 'timedOut' => false];
        }

        fwrite($pipes[0], json_encode($payload));
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $stderr = '';
        $exitCode = null;
        $status = ['running' => true];
        $deadline = time() + max(5, $timeoutSecs);
        do {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $ready = stream_select($read, $write, $except, 1);
            if ($ready !== false) {
                foreach ($read as $s) {
                    $chunk = stream_get_contents($s);
                    if ($chunk !== false && $chunk !== '') {
                        if ($s === $pipes[1]) {
                            $output .= $chunk;
                        } else {
                            $stderr .= $chunk;
                        }
                    }
                }
            }
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                $output .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                break;
            }
        } while (time() < $deadline);

        if (($status['running'] ?? false)) {
            proc_terminate($process);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return ['ok' => true, 'output' => $output, 'stderr' => $stderr, 'python' => $pythonExec, 'exit' => $exitCode, 'timedOut' => true];
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return ['ok' => true, 'output' => $output, 'stderr' => $stderr, 'python' => $pythonExec, 'exit' => $exitCode, 'timedOut' => false];
    }
}

if (!function_exists('ml_audit')) {
    function ml_audit(string $event, string $employeeUid, array $context): void
    {
        if (!function_exists('audit_log_event')) {
            return;
        }
        try {
            audit_log_event($event, $employeeUid, $context);
        } catch (\Throwable $e) {
            // audit must never break evaluation
        }
    }
}
