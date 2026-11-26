<?php
declare(strict_types=1);

function maintaintrack_env(string $name, string $default = ''): string
{
    $process = getenv($name);
    if ($process !== false && trim($process) !== '') return trim($process);

    static $settings = null;
    if ($settings === null) {
        $settings = [];
        $explicit = getenv('MAINTAINTRACK_ENV_FILE');
        $paths = $explicit !== false && trim($explicit) !== ''
            ? [trim($explicit)]
            : [dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'MaintainTrack.env',
               dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env'];
        foreach ($paths as $path) {
            if (!is_readable($path)) continue;
            $parsed = @parse_ini_file($path, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                $settings = $parsed;
                break;
            }
        }
    }
    $value = trim((string) ($settings[$name] ?? ''));
    return $value !== '' ? $value : $default;
}
