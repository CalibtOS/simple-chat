<?php

final class Logger
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $line = [
            'time' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
        ];
        @file_put_contents($this->logFile, json_encode($line) . PHP_EOL, FILE_APPEND);
    }
}

