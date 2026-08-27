<?php

namespace App\Console\Commands;

use App\Support\EncryptedEnv;
use Illuminate\Console\Command;

class EncryptEnvSecretsCommand extends Command
{
    protected $signature = 'secrets:encrypt-env
                            {--path= : Absolute path to the .env file (defaults to base_path(.env))}
                            {--dry-run : Show which keys would be encrypted without writing}';

    protected $description = 'Encrypt configured API/S3 secrets in .env (enc:…). Plaintext values still work until migrated.';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: base_path('.env'));

        if (! is_readable($path)) {
            $this->error('Cannot read '.$path);

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($path);
        $updated = $contents;
        $changed = [];

        foreach (EncryptedEnv::secretEnvKeys() as $key) {
            if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $updated, $matches)) {
                continue;
            }

            $raw = trim((string) $matches[1]);
            $plain = $this->unquoteEnvValue($raw);

            if ($plain === '' || EncryptedEnv::isEncrypted($plain)) {
                continue;
            }

            $sealed = EncryptedEnv::seal($plain);
            $line = $key.'="'.$this->escapeEnvDoubleQuoted($sealed).'"';
            $updated = preg_replace(
                '/^'.preg_quote($key, '/').'=.*$/m',
                $line,
                $updated,
                1,
            );
            $changed[] = $key;
        }

        if ($changed === []) {
            $this->info('No plaintext secrets needed encryption.');

            return self::SUCCESS;
        }

        $this->info(($this->option('dry-run') ? 'Would encrypt' : 'Encrypted').': '.implode(', ', $changed));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (file_put_contents($path, $updated) === false) {
            $this->error('Failed to write '.$path);

            return self::FAILURE;
        }

        $this->comment('Values are encrypted at rest. The app decrypts them in memory when used.');

        return self::SUCCESS;
    }

    private function unquoteEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $quote = $value[0];
            $inner = substr($value, 1, -1);

            return $quote === '"'
                ? stripcslashes($inner)
                : str_replace("\\'", "'", $inner);
        }

        return $value;
    }

    private function escapeEnvDoubleQuoted(string $value): string
    {
        return str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);
    }
}
