<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Bootstrap;

use Dotenv\Dotenv;
use Dotenv\Exception\Invalid_File_Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Env;
use Symfony\Component\Console\Input\Argv_Input;
use Symfony\Component\Console\Output\Console_Output;
class Load_Environment_Variables
{
    /**
     * Bootstrap the given application.
     */
    public function bootstrap(Application $app): void
    {
        if ($app->configuration_is_cached()) {
            return;
        }
        $this->check_for_specific_environment_file($app);
        try {
            $this->create_dotenv($app)->safe_load();
        } catch (Invalid_File_Exception $e) {
            $this->write_error_and_die($e);
        }
    }
    /**
     * Detect if a custom environment file matching the APP_ENV exists.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    protected function check_for_specific_environment_file($app)
    {
        if ($app->running_in_console() && ($input = new Argv_Input())->has_parameter_option('--env') && $this->set_environment_file_path($app, $app->environment_file() . '.' . $input->get_parameter_option('--env'))) {
            return;
        }
        $environment = Env::get('APP_ENV');
        if (!$environment) {
            return;
        }
        $this->set_environment_file_path($app, $app->environment_file() . '.' . $environment);
    }
    /**
     * Load a custom environment file.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    protected function set_environment_file_path($app, string $file): bool
    {
        if (is_file($app->environment_path() . '/' . $file)) {
            $app->load_environment_from($file);
            return true;
        }
        return false;
    }
    /**
     * Create a Dotenv instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return \Dotenv\Dotenv
     */
    protected function create_dotenv($app)
    {
        return Dotenv::create(Env::get_repository(), $app->environment_path(), $app->environment_file());
    }
    /**
     * Write the error information to the screen and exit.
     *
     * @param  \Dotenv\Exception\InvalidFileException  $e
     */
    protected function write_error_and_die(Invalid_File_Exception $e): never
    {
        $output = (new Console_Output())->get_error_output();
        $output->writeln('The environment file is invalid!');
        $output->writeln($e->get_message());
        http_response_code(500);
        exit(1);
    }
}