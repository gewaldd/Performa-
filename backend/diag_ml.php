<?php
/**
 * Performa ML Diagnostics (browser, Apache service context)
 * Location: backend/diag_ml.php
 *
 * Shows which Python the web app resolves, whether RF deps exist for THAT
 * interpreter, and whether GEMINI_API_KEY reaches PHP — without printing secrets.
 * Manuscript: supports P.502-504 audit traceability; no classification logic here.
 *
 * Access: open http://localhost/<app>/backend/diag_ml.php while logged in as
 * employer/admin. Web access requires an employer or admin session; CLI access
 * (PHP_SAPI === 'cli', for ops verification) is allowed without a session.
 * No writes, no Firestore calls, never prints secrets.
 */
require_once __DIR__ . '/ml_bridge.php';

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/auth.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_login();
    $diagRole = (string) ($_SESSION['role'] ?? '');
    if ($diagRole !== 'employer' && stripos($diagRole, 'admin') === false) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Access denied. Employer or admin role required.']);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

$python = ml_resolve_python_exec();
$script = realpath(__DIR__ . '/../ml/gemini_recommender.py');
$modelFile = realpath(__DIR__ . '/../ml/models/random_forest_v1.joblib');

function try_py(string $python, string $code, string $cwd): array
{
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $cmd = "\"{$python}\" -c " . escapeshellarg($code);
    $p = proc_open($cmd, $descriptors, $pipes, $cwd, ml_build_env());
    if (!is_resource($p)) {
        return ['ok' => false, 'out' => '', 'err' => 'proc_open failed'];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = '';
    $err = '';
    $deadline = time() + 20;
    do {
        $r = [$pipes[1], $pipes[2]];
        $w = null;
        $e = null;
        if (stream_select($r, $w, $e, 1) !== false) {
            foreach ($r as $s) {
                $chunk = stream_get_contents($s);
                if ($chunk !== false && $chunk !== '') {
                    if ($s === $pipes[1]) {
                        $out .= $chunk;
                    } else {
                        $err .= $chunk;
                    }
                }
            }
        }
        $st = proc_get_status($p);
        if (!$st['running']) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = $st['exitcode'];
            proc_close($p);
            return ['ok' => true, 'out' => trim($out), 'err' => trim($err), 'exit' => $exit];
        }
    } while (time() < $deadline);
    proc_terminate($p);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($p);
    return ['ok' => true, 'out' => trim($out), 'err' => trim($err), 'exit' => null, 'timedOut' => true];
}

$cwd = dirname((string) $script);
$deps = try_py($python, "import sklearn,joblib,pandas; print(sklearn.__version__)", $cwd);
$genai = try_py($python, "import google.genai; print('genai-ok')", $cwd);
$rfSmoke = try_py(
    $python,
    "from predict import predict_competencies; import json; r=predict_competencies('DIAG','2026-09',{'task_completion':4.5,'attendance_punctuality':4.5,'communication_teamwork':4.5,'initiative_adaptability':4.5,'quality_of_work':4.5}); print(json.dumps({'overall':r['overall_prediction'],'model_version':r.get('model_version')}))",
    $cwd
);

$key = getenv('GEMINI_API_KEY') ?: '';

echo json_encode([
    'python' => $python,
    'script_found' => ($script !== false),
    'model_found' => ($modelFile !== false),
    'model_file' => $modelFile ?: 'ml/models/random_forest_v1.joblib (missing - run ml/train.py)',
    'gemini_key_present' => ($key !== ''),
    'gemini_key_length' => strlen($key),
    'deps_sklearn_joblib_pandas' => $deps,
    'dep_google_genai' => $genai,
    'rf_smoke' => $rfSmoke,
    'env_hint' => 'Set PYTHON_EXEC to the full path of the Python that has sklearn to override resolution.',
], JSON_PRETTY_PRINT);
