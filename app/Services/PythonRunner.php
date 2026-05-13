<?php

namespace App\Services;

use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

/**
 * Thin wrapper around Laravel's Process facade to invoke the python
 * scripts living under `python/`. Every script reads JSON on stdin
 * and writes one JSON line of result on stdout — see python/_common.py.
 *
 * Why a service class instead of inline Process::run() calls?
 *
 *  1. Keeps the Python invocation contract in one place. If we ever
 *     swap to a Python HTTP service, only this class changes.
 *  2. Centralizes payload serialization, stderr logging and JSON parsing.
 *  3. Lets jobs stay focused on domain logic (update segment row, fire
 *     next step) instead of fiddling with subprocess plumbing.
 */
class PythonRunner
{
    public function __construct(
        private readonly string $pythonBin,
        private readonly string $scriptsPath,
    ) {}

    /**
     * Run a python script with the given payload and return the parsed
     * JSON result. Throws on non-zero exit.
     *
     * @param string $script  filename under python/ (e.g. 'fetch_source.py')
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function run(string $script, array $payload, int $timeoutSeconds = 1800): array
    {
        $scriptPath = $this->scriptsPath.DIRECTORY_SEPARATOR.$script;

        if (! is_file($scriptPath)) {
            throw new RuntimeException("Python script not found: {$scriptPath}");
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $result = Process::timeout($timeoutSeconds)
            ->path($this->scriptsPath)
            ->env($this->processEnv())
            ->input($json)
            ->run([$this->pythonBin, $scriptPath]);

        if ($result->failed()) {
            throw new RuntimeException(sprintf(
                "Python script %s failed (exit %d): %s",
                $script,
                $result->exitCode(),
                trim($result->errorOutput())
            ));
        }

        $stdout = trim($result->output());
        if ($stdout === '') {
            throw new RuntimeException("Python script {$script} produced no stdout");
        }

        // The last line of stdout is the JSON result (other lines, if any,
        // are diagnostic prints from the script).
        $lines = preg_split('/\r?\n/', $stdout) ?: [];
        $jsonLine = end($lines);

        try {
            $parsed = json_decode($jsonLine, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Python script {$script} returned invalid JSON: ".$e->getMessage());
        }

        if (! is_array($parsed)) {
            throw new RuntimeException("Python script {$script} did not return a JSON object");
        }

        return $parsed;
    }

    /** Inject relevant env vars so the script can read them via os.environ. */
    private function processEnv(): array
    {
        return array_filter([
            'AI_PROVIDER'    => config('pipeline.ai.provider'),
            'OPENAI_API_KEY' => config('pipeline.ai.providers.openai.api_key'),
            'GEMINI_API_KEY' => config('pipeline.ai.providers.gemini.api_key'),
            'EDGE_TTS_VOICE' => config('pipeline.tts.voice'),
        ]);
    }
}
