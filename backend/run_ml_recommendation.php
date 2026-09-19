<?php
/**
 * Performa Backend - ML Pipeline Execution Wrapper
 * Location: backend/run_ml_recommendation.php
 */

header('Content-Type: application/json; charset=utf-8');

function evaluateEmployeePerformance(
    string $employeeUid,
    string $evaluationMonth,
    array $categoryScores,
    string $jobRole = "Probationary Employee",
    string $industry = "General"
): array {
    // 1. Path to your Python executable and script
    $pythonExec = "C:\\Users\\andrew\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe";
    $scriptPath = realpath(__DIR__ . "/../ml/gemini_recommender.py");

    if (!$scriptPath || !file_exists($scriptPath)) {
        return [
            "status" => "error",
            "message" => "Script not found at: {$scriptPath}"
        ];
    }

    // 2. Prepare payload for Python STDIN
    $payload = [
        "employee_uid" => $employeeUid,
        "evaluation_month" => $evaluationMonth,
        "job_role" => $jobRole,
        "industry" => $industry,
        "category_scores" => [
            "task_completion" => floatval($categoryScores['task_completion'] ?? 3.0),
            "attendance_punctuality" => floatval($categoryScores['attendance_punctuality'] ?? 3.0),
            "communication_teamwork" => floatval($categoryScores['communication_teamwork'] ?? 3.0),
            "initiative_adaptability" => floatval($categoryScores['initiative_adaptability'] ?? 3.0),
            "quality_of_work" => floatval($categoryScores['quality_of_work'] ?? 3.0)
        ]
    ];

    // 3. Open process pipes
    $descriptorspec = [
        0 => ["pipe", "r"], // stdin
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"]  // stderr
    ];

    $command = "\"{$pythonExec}\" \"{$scriptPath}\" --input-stdin";
    $process = proc_open($command, $descriptorspec, $pipes, dirname($scriptPath));

    if (is_resource($process)) {
        // Send JSON data to Python via stdin
        fwrite($pipes[0], json_encode($payload));
        fclose($pipes[0]);

        // Get output from Python
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);

        $decoded = json_decode(trim($output), true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return [
                "status" => "success",
                "data" => $decoded
            ];
        }

        return [
            "status" => "error",
            "message" => "Failed to parse Python JSON output.",
            "raw_output" => $output,
            "stderr" => $stderr
        ];
    }

    return [
        "status" => "error",
        "message" => "Failed to start Python process."
    ];
}