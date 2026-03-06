<?php

declare(strict_types=1);

namespace Illuminate\Support;

use Closure;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Composer
{
    /**
     * Create a new Composer manager instance.
     *
     * @param  string|null  $workingPath
     */
    public function __construct(
        /**
         * The filesystem instance.
         */
        protected \Illuminate\Filesystem\Filesystem $files,
        /**
         * The working path to regenerate from.
         */
        protected $workingPath = null
    ) {
    }

    /**
     * Determine if the given Composer package is installed.
     *
     * @param  string  $package
     *
     * @throws \RuntimeException
     */
    public function hasPackage($package): bool
    {
        $composer = json_decode(file_get_contents($this->findComposerFile()), true);

        return array_key_exists($package, $composer['require'] ?? [])
            || array_key_exists($package, $composer['require-dev'] ?? []);
    }

    /**
     * Install the given Composer packages into the application.
     *
     * @param  array<int, string>  $packages
     * @param  string|null  $composerBinary
     */
    public function requirePackages(array $packages, bool $dev = false, Closure|OutputInterface|null $output = null, $composerBinary = null): bool
    {
        $command = (new Collection([
            ...$this->findComposer($composerBinary),
            'require',
            ...$packages,
        ]))
            ->when($dev, function ($command): void {
                $command->push('--dev');
            })->all();

        return 0 === $this->getProcess($command, ['COMPOSER_MEMORY_LIMIT' => '-1'])
            ->run(
                $output instanceof OutputInterface
                    ? function ($type, string $line) use ($output): void {
                        $output->write('    '.$line);
                    } : $output
            );
    }

    /**
     * Remove the given Composer packages from the application.
     *
     * @param  array<int, string>  $packages
     * @param  string|null  $composerBinary
     */
    public function removePackages(array $packages, bool $dev = false, Closure|OutputInterface|null $output = null, $composerBinary = null): bool
    {
        $command = (new Collection([
            ...$this->findComposer($composerBinary),
            'remove',
            ...$packages,
        ]))
            ->when($dev, function ($command): void {
                $command->push('--dev');
            })->all();

        return 0 === $this->getProcess($command, ['COMPOSER_MEMORY_LIMIT' => '-1'])
            ->run(
                $output instanceof OutputInterface
                    ? function ($type, string $line) use ($output): void {
                        $output->write('    '.$line);
                    } : $output
            );
    }

    /**
     * Modify the "composer.json" file contents using the given callback.
     *
     * @param  callable(array):array  $callback
     *
     * @throws \RuntimeException
     */
    public function modify(callable $callback): void
    {
        $composerFile = $this->findComposerFile();

        $composer = json_decode(file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);

        file_put_contents(
            $composerFile,
            json_encode(
                call_user_func($callback, $composer),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );
    }

    /**
     * Regenerate the Composer autoloader files.
     *
     * @param  string|array  $extra
     * @param  string|null  $composerBinary
     */
    public function dumpAutoloads($extra = '', $composerBinary = null): int
    {
        $extra = $extra ? (array) $extra : [];

        $command = array_merge($this->findComposer($composerBinary), ['dump-autoload'], $extra);

        return $this->getProcess($command)->run();
    }

    /**
     * Regenerate the optimized Composer autoloader files.
     *
     * @param  string|null  $composerBinary
     */
    public function dumpOptimized($composerBinary = null): int
    {
        return $this->dumpAutoloads('--optimize', $composerBinary);
    }

    /**
     * Get the Composer binary / command for the environment.
     *
     * @param  string|null  $composerBinary
     */
    public function findComposer($composerBinary = null): array
    {
        if (! is_null($composerBinary) && $this->files->exists($composerBinary)) {
            return [$this->phpBinary(), $composerBinary];
        }
        if ($this->files->exists($this->workingPath.'/composer.phar')) {
            return [$this->phpBinary(), 'composer.phar'];
        }

        return ['composer'];
    }

    /**
     * Get the path to the "composer.json" file.
     *
     *
     * @throws \RuntimeException
     */
    protected function findComposerFile(): string
    {
        $composerFile = "{$this->workingPath}/composer.json";

        if (! file_exists($composerFile)) {
            throw new RuntimeException("Unable to locate `composer.json` file at [{$this->workingPath}].");
        }

        return $composerFile;
    }

    /**
     * Get the PHP binary.
     */
    protected function phpBinary(): string
    {
        return php_binary();
    }

    /**
     * Get a new Symfony process instance.
     */
    protected function getProcess(array $command, array $env = []): \Symfony\Component\Process\Process
    {
        return (new Process($command, $this->workingPath, $env))->setTimeout(null);
    }

    /**
     * Set the working path used by the class.
     *
     * @param  string  $path
     * @return $this
     */
    public function setWorkingPath($path): static
    {
        $this->workingPath = realpath($path);

        return $this;
    }

    /**
     * Get the version of Composer.
     */
    public function getVersion(): ?string
    {
        $command = array_merge($this->findComposer(), ['-V', '--no-ansi']);

        $process = $this->getProcess($command);

        $process->run();

        $output = $process->getOutput();

        if (preg_match('/(\d+(\.\d+){2})/', $output, $version)) {
            return $version[1];
        }

        return explode(' ', $output)[2] ?? null;
    }
}
