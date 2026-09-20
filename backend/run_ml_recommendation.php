<?php
/**
 * Performa Backend - ML Pipeline Execution Wrapper
 * Location: backend/run_ml_recommendation.php
 *
 * Manuscript alignment: invokes ml/gemini_recommender.py --input-stdin which
 * runs real RF inference (predict.py) + Gemini recommendations.
 * Shared execution via backend/ml_bridge.php (resolver + timeout + audit).
 */

require_once __DIR__ . '/ml_bridge.php';

if (!function_exists('resolvePythonExec')) {
    function resolvePythonExec(): string
    {
        return ml_resolve_python_exec();
    }
}

if (!function_exists('evaluateEmployeePerformance')) {
    function evaluateEmployeePerformance(
        string $employeeUid,
        string $evaluationMonth,
        array $categoryScores,
        string $jobRole = "Probationary Employee",
        string $industry = "General",
        int $timeoutSecs = ML_INVOKE_TIMEOUT_SECS
    ): array {
        $scriptPath = realpath(__DIR__ . "/../ml/gemini_recommender.py");

        if (!$scriptPath || !file_exists($scriptPath)) {
            return [
                "status" => "error",
                "message" => "ML script not found: " . (__DIR__ . "/../ml/gemini_recommender.py"),
            ];
        }

        $payload = [
            "employee_uid" => $employeeUid,
            "evaluation_month" => $evaluationMonth,
            "job_role" => $jobRole,
            "industry" => $industry,
            "category_scores" => ml_canonical_scores($categoryScores),
        ];

        $res = ml_invoke_stdin($scriptPath, $payload, $timeoutSecs);

        if (!$res['ok']) {
            return ["status" => "error", "message" => "Failed to start Python process."];
        }
        if ($res['timedOut']) {
            return ["status" => "error", "message" => "ML process timed out after {$timeoutSecs}s.", "stderr" => substr($res['stderr'], 0, 2000)];
        }

        $decoded = json_decode(trim($res['output']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            ml_audit('ml_recommendation', $employeeUid, [
                'month' => $evaluationMonth,
                'industry' => $industry,
                'model' => $decoded['model_version'] ?? 'unknown',
                'exit' => $res['exit'],
            ]);
            return ["status" => "success", "data" => $decoded, "python" => $res['python']];
        }

        return [
            "status" => "error",
            "message" => "Failed to parse Python JSON output.",
            "raw_output" => substr($res['output'], 0, 4000),
            "stderr" => substr($res['stderr'], 0, 4000),
            "python" => $res['python'],
            "exit" => $res['exit'],
        ];
    }
}
