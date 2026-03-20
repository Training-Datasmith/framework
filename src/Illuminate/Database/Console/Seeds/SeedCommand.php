<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Seeds;

use Illuminate\Console\Command;
use Illuminate\Console\Confirmable_Trait;
use Illuminate\Console\Prohibitable;
use Illuminate\Database\Connection_Resolver_Interface as Resolver;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Option;
#[As_Command(name: 'db:seed')]
class Seed_Command extends Command
{
    use Confirmable_Trait;
    use Prohibitable;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'db:seed';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with records';
    /**
     * Create a new database seed command instance.
     */
    public function __construct(
        /**
         * The connection resolver instance.
         */
        protected \Illuminate\Database\Connection_Resolver_Interface $resolver
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->is_prohibited() || !$this->confirm_to_proceed()) {
            return Command::FAILURE;
        }
        $this->components->info('Seeding database.');
        $previous_connection = $this->resolver->get_default_connection();
        $this->resolver->set_default_connection($this->get_database());
        Model::unguarded(function (): void {
            $this->get_seeder()->__invoke();
        });
        if ($previous_connection) {
            $this->resolver->set_default_connection($previous_connection);
        }
        return 0;
    }
    /**
     * Get a seeder instance from the container.
     *
     * @return \Illuminate\Database\Seeder
     */
    protected function get_seeder()
    {
        $class = $this->input->get_argument('class') ?? $this->input->get_option('class');
        if (!str_contains($class, '\\')) {
            $class = 'Database\Seeders\\' . $class;
        }
        if ($class === 'Database\Seeders\DatabaseSeeder' && !class_exists($class)) {
            $class = 'DatabaseSeeder';
        }
        return $this->laravel->make($class)->set_container($this->laravel)->set_command($this);
    }
    /**
     * Get the name of the database connection to use.
     *
     * @return string
     */
    protected function get_database()
    {
        $database = $this->input->get_option('database');
        return $database ?: $this->laravel['config']['database.default'];
    }
    /**
     * Get the console command arguments.
     */
    protected function get_arguments(): array
    {
        return [['class', Input_Argument::OPTIONAL, 'The class name of the root seeder', null]];
    }
    /**
     * Get the console command options.
     */
    protected function get_options(): array
    {
        return [['class', null, Input_Option::VALUE_OPTIONAL, 'The class name of the root seeder', 'Database\Seeders\DatabaseSeeder'], ['database', null, Input_Option::VALUE_OPTIONAL, 'The database connection to seed'], ['force', null, Input_Option::VALUE_NONE, 'Force the operation to run when in production']];
    }
}