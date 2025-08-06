<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class CronManager
{
    protected static string $sudo = '/usr/bin/sudo';
    protected static string $crontab = '/usr/bin/crontab';
    protected static string $user = 'root';

    public static function remove(string $pattern)
    {
        $lines = [];
        exec(self::buildCommand(['-l']), $lines, $ret);

        $output = implode("\n", $lines);

        if ($ret !== 0) {
            if (stripos($output, 'no crontab for') !== false || stripos($output, 'no crontab') !== false) {
                $lines = [];
            } else {
                throw new RuntimeException("Falha ao listar o crontab: {$output}");
            }
        }

        $filtered = array_filter($lines, function ($l) use ($pattern) {
            return !preg_match($pattern, $l);
        });

        return self::write($filtered);
    }

    public static function add(string $jobLine)
    {
        $lines = [];
        exec(self::buildCommand(['-l']), $lines, $ret);

        $output = implode("\n", $lines);

        if ($ret !== 0) {
            if (stripos($output, 'no crontab') !== false) {
                $lines = [];
            } else {
                throw new RuntimeException("Falha ao listar o crontab: {$output}");
            }
        }

        if (!in_array($jobLine, $lines, true)) {
            $lines[] = $jobLine;
        }

        return self::write($lines);
    }

    protected static function write(array $lines)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tmp, implode("\n", $lines) . "\n");

        $out = [];
        exec(self::buildCommand([$tmp]), $out, $ret);
        unlink($tmp);

        if ($ret !== 0) {
            $outStr = implode("\n", $out);
            throw new RuntimeException("Falha ao gravar o crontab: {$outStr}");
        }

        return true;
    }

    public static function list()
    {
        $output = [];
        exec(self::buildCommand(['-l']), $output, $return);

        $outStr = implode("\n", $output);
        if ($return !== 0) {
            if (stripos($outStr, 'no crontab') !== false) {
                return []; // Nenhuma tarefa configurada
            } else {
                throw new \RuntimeException("Não foi possível listar o crontab: {$outStr}");
            }
        }

        return $output;
    }

    protected static function buildCommand(array $args): string
    {
        $argsStr = implode(' ', array_map('escapeshellarg', $args));
        return sprintf(
            '%s %s -u %s %s 2>&1',
            self::$sudo,
            self::$crontab,
            self::$user,
            $argsStr
        );
    }
}

