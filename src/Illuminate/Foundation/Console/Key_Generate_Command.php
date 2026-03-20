<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Confirmable_Trait;
use Illuminate\Encryption\Encrypter;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'key:generate')]
class Key_Generate_Command extends Command
{
    use Confirmable_Trait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'key:generate
                    {--show : Display the key instead of modifying files}
                    {--force : Force the operation to run when in production}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the application key';
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $key = $this->generate_random_key();
        if ($this->option('show')) {
            return $this->line('<comment>' . $key . '</comment>');
        }
        // Next, we will replace the application key in the environment file so it is
        // automatically setup for this developer. This key gets generated using a
        // secure random byte generator and is later base64 encoded for storage.
        if (!$this->set_key_in_environment_file($key)) {
            return;
        }
        $this->laravel['config']['app.key'] = $key;
        $this->components->info('Application key set successfully.');
    }
    /**
     * Generate a random key for the application.
     */
    protected function generate_random_key(): string
    {
        return 'base64:' . base64_encode(Encrypter::generate_key($this->laravel['config']['app.cipher']));
    }
    /**
     * Set the application key in the environment file.
     *
     * @param  string  $key
     */
    protected function set_key_in_environment_file($key): bool
    {
        $current_key = $this->laravel['config']['app.key'];
        if (strlen((string) $current_key) !== 0 && !$this->confirm_to_proceed()) {
            return false;
        }
        if (!$this->write_new_environment_file_with($key)) {
            return false;
        }
        return true;
    }
    /**
     * Write a new environment file with the given key.
     */
    protected function write_new_environment_file_with(string $key): bool
    {
        $replaced = preg_replace($this->key_replacement_pattern(), 'APP_KEY=' . $key, $input = file_get_contents($this->laravel->environment_file_path()));
        if ($replaced === $input || $replaced === null) {
            if (isset($_ENV['APP_KEY'])) {
                $this->components->error('Unable to set application key. APP_KEY is already present in the environment.');
            } else {
                $this->components->error('Unable to set application key. No APP_KEY variable was found in the .env file.');
            }
            return false;
        }
        file_put_contents($this->laravel->environment_file_path(), $replaced);
        return true;
    }
    /**
     * Get a regex pattern that will match env APP_KEY with any random key.
     */
    protected function key_replacement_pattern(): string
    {
        $escaped = preg_quote('=' . $this->laravel['config']['app.key'], '/');
        return "/^APP_KEY{$escaped}/m";
    }
}