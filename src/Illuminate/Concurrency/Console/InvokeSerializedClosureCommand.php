<?php

declare (strict_types=1);
namespace Illuminate\Concurrency\Console;

use Illuminate\Console\Command;
use ReflectionClass;
use Symfony\Component\Console\Attribute\As_Command;
use Throwable;
#[As_Command(name: 'invoke-serialized-closure')]
class Invoke_Serialized_Closure_Command extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'invoke-serialized-closure {code? : The serialized closure}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invoke the given serialized closure';
    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;
    /**
     * Execute the console command.
     *
     *
     * @throws \RuntimeException
     */
    public function handle(): void
    {
        try {
            $this->output->write(json_encode(['successful' => true, 'result' => serialize($this->laravel->call(match (true) {
                !is_null($this->argument('code')) => unserialize($this->argument('code')),
                isset($_SERVER['LARAVEL_INVOKABLE_CLOSURE']) => unserialize(base64_decode((string) $_SERVER['LARAVEL_INVOKABLE_CLOSURE'])),
                default => fn(): null => null,
            }))]));
        } catch (Throwable $e) {
            report($e);
            $reflection = new ReflectionClass($e);
            $constructor = $reflection->get_constructor();
            $parameters = [];
            if ($constructor) {
                $declaring_class = $constructor->get_declaring_class()->get_name();
                if ($declaring_class === $reflection->get_name()) {
                    foreach ($constructor->get_parameters() as $parameter) {
                        $parameters[$parameter->name] = $e->{$parameter->name} ?? null;
                    }
                }
            }
            $this->output->write(json_encode(['successful' => false, 'exception' => $e::class, 'message' => $e->get_message(), 'file' => $e->get_file(), 'line' => $e->get_line(), 'parameters' => $parameters]));
        }
    }
}