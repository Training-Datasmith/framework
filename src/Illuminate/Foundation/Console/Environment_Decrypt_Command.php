<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Dotenv\Parser\Lines;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Env;
use Illuminate\Support\Str;
use function Laravel\Prompts\password;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'env:decrypt')]
class Environment_Decrypt_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'env:decrypt
                    {--key= : The encryption key}
                    {--cipher= : The encryption cipher}
                    {--env= : The environment to be decrypted}
                    {--force : Overwrite the existing environment file}
                    {--path= : Path to write the decrypted file}
                    {--filename= : Filename of the decrypted file}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decrypt an environment file';
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
        $key = $this->option('key') ?: Env::get('LARAVEL_ENV_ENCRYPTION_KEY');
        if (!$key && $this->input->is_interactive()) {
            $key = password('What is the decryption key?');
        }
        if (!$key) {
            $this->fail('A decryption key is required.');
        }
        $cipher = $this->option('cipher') ?: 'AES-256-CBC';
        $key = $this->parse_key($key);
        $encrypted_file = ($this->option('env') ? Str::finish($this->laravel->environment_path(), DIRECTORY_SEPARATOR) . '.env.' . $this->option('env') : $this->laravel->environment_file_path()) . '.encrypted';
        $output_file = $this->output_file_path();
        if (Str::ends_with($output_file, '.encrypted')) {
            $this->fail('Invalid filename.');
        }
        if (!$this->files->exists($encrypted_file)) {
            $this->fail('Encrypted environment file not found.');
        }
        if ($this->files->exists($output_file) && !$this->option('force')) {
            $this->fail('Environment file already exists.');
        }
        try {
            $encrypter = new Encrypter($key, $cipher);
            $encrypted_contents = $this->files->get($encrypted_file);
            $decrypted = $this->is_readable_format($encrypted_contents) ? $this->decrypt_readable_format($encrypted_contents, $encrypter) : $encrypter->decrypt($encrypted_contents);
            $this->files->put($output_file, $decrypted);
        } catch (Exception $e) {
            $this->fail($e->get_message());
        }
        $this->components->info('Environment successfully decrypted.');
        $this->components->two_column_detail('Decrypted file', $output_file);
        $this->new_line();
    }
    /**
     * Determine if the content is in readable format where each variable still has its own plain-text key.
     */
    protected function is_readable_format(string $contents): bool
    {
        return !Encrypter::appears_encrypted($contents);
    }
    /**
     * Decrypt the environment file from readable format.
     */
    protected function decrypt_readable_format(string $contents, Encrypter $encrypter): string
    {
        $result = '';
        foreach (Lines::process(preg_split('/\r\n|\r|\n/', $contents)) as $entry) {
            $pos = strpos($entry, '=');
            if ($pos === false) {
                continue;
            }
            $name = substr($entry, 0, $pos);
            $encrypted_value = substr($entry, $pos + 1);
            $result .= $name . '=' . $encrypter->decrypt_string($encrypted_value) . "\n";
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
    /**
     * Get the output file path that should be used for the command.
     */
    protected function output_file_path(): string
    {
        $path = Str::finish($this->option('path') ?: $this->laravel->environment_path(), DIRECTORY_SEPARATOR);
        $output_file = $this->option('filename') ?: '.env' . ($this->option('env') ? '.' . $this->option('env') : '');
        $output_file = ltrim($output_file, DIRECTORY_SEPARATOR);
        return $path . $output_file;
    }
}