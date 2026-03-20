<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Dotenv\Parser\Lines;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'env:encrypt')]
class Environment_Encrypt_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'env:encrypt
                    {--key= : The encryption key}
                    {--cipher= : The encryption cipher}
                    {--env= : The environment to be encrypted}
                    {--readable : Encrypt each variable individually with readable, plain-text variable names}
                    {--prune : Delete the original environment file}
                    {--force : Overwrite the existing encrypted environment file}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt an environment file';
    /**
     * Create a new command instance.
     */
    public function __construct(
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cipher = $this->option('cipher') ?: 'AES-256-CBC';
        $key = $this->option('key');
        if (!$key && $this->input->is_interactive()) {
            $ask = select(label: 'What encryption key would you like to use?', options: ['generate' => 'Generate a random encryption key', 'ask' => 'Provide an encryption key'], default: 'generate');
            if ($ask == 'ask') {
                $key = password('What is the encryption key?');
            }
        }
        $key_passed = $key !== null;
        $environment_file = $this->option('env') ? Str::finish($this->laravel->environment_path(), DIRECTORY_SEPARATOR) . '.env.' . $this->option('env') : $this->laravel->environment_file_path();
        $encrypted_file = $environment_file . '.encrypted';
        if (!$key_passed) {
            $key = Encrypter::generate_key($cipher);
        }
        if (!$this->files->exists($environment_file)) {
            $this->fail('Environment file not found.');
        }
        if ($this->files->exists($encrypted_file) && !$this->option('force')) {
            $this->fail('Encrypted environment file already exists.');
        }
        try {
            $encrypter = new Encrypter($this->parse_key($key), $cipher);
            $contents = $this->files->get($environment_file);
            $encrypted = $this->option('readable') ? $this->encrypt_readable_format($contents, $encrypter) : $encrypter->encrypt($contents);
            $this->files->put($encrypted_file, $encrypted);
        } catch (Exception $e) {
            $this->fail($e->get_message());
        }
        if ($this->option('prune')) {
            $this->files->delete($environment_file);
        }
        $this->components->info('Environment successfully encrypted.');
        $this->components->two_column_detail('Key', $key_passed ? $key : 'base64:' . base64_encode((string) $key));
        $this->components->two_column_detail('Cipher', $cipher);
        $this->components->two_column_detail('Encrypted file', $encrypted_file);
        $this->new_line();
    }
    /**
     * Encrypt the environment file in readable format.
     */
    protected function encrypt_readable_format(string $contents, Encrypter $encrypter): string
    {
        $result = '';
        foreach (Lines::process(preg_split('/\r\n|\r|\n/', $contents)) as $entry) {
            $pos = strpos($entry, '=');
            if ($pos === false) {
                continue;
            }
            $name = substr($entry, 0, $pos);
            $value = substr($entry, $pos + 1);
            $result .= $name . '=' . $encrypter->encrypt_string($value) . "\n";
        }
        return $result;
    }
    /**
     * Parse the encryption key.
     *
     * @return string
     */
    protected function parse_key(string $key): string|false
    {
        if (Str::starts_with($key, $prefix = 'base64:')) {
            return base64_decode(Str::after($key, $prefix));
        }
        return $key;
    }
}