<?php

declare (strict_types=1);
namespace Illuminate\Database\Console\Migrations;

use Illuminate\Contracts\Console\Prompts_For_Missing_Input;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\As_Command;
#[As_Command(name: 'make:migration')]
class Migrate_Make_Command extends Base_Command implements Prompts_For_Missing_Input
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'make:migration {name : The name of the migration}
        {--create= : The table to be created}
        {--table= : The table to migrate}
        {--path= : The location where the migration file should be created}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
        {--fullpath : Output the full path of the migration (Deprecated)}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration file';
    /**
     * Create a new migration install command instance.
     */
    public function __construct(
        /**
         * The migration creator instance.
         */
        protected \Illuminate\Database\Migrations\Migration_Creator $creator,
        /**
         * The Composer instance.
         *
         *
         * @deprecated Will be removed in a future Laravel version.
         */
        protected \Illuminate\Support\Composer $composer
    )
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // It's possible for the developer to specify the tables to modify in this
        // schema operation. The developer may also specify if this table needs
        // to be freshly created so we can create the appropriate migrations.
        $name = Str::snake(trim((string) $this->input->get_argument('name')));
        $table = $this->input->get_option('table');
        $create = $this->input->get_option('create') ?: false;
        // If no table was given as an option but a create option is given then we
        // will use the "create" option as the table name. This allows the devs
        // to pass a table name into this option as a short-cut for creating.
        if (!$table && is_string($create)) {
            $table = $create;
            $create = true;
        }
        // Next, we will attempt to guess the table name if this the migration has
        // "create" in the name. This will allow us to provide a convenient way
        // of creating migrations that create new tables for the application.
        if (!$table) {
            [$table, $create] = Table_Guesser::guess($name);
        }
        // Now we are ready to write the migration out to disk. Once we've written
        // the migration out, we will dump-autoload for the entire framework to
        // make sure that the migrations are registered by the class loaders.
        $this->write_migration($name, $table, $create);
    }
    /**
     * Write the migration file to disk.
     *
     * @param  string  $name
     * @param  string  $table
     * @param  bool  $create
     * @return void
     */
    protected function write_migration($name, $table, $create)
    {
        $file = $this->creator->create($name, $this->get_migration_path(), $table, $create);
        if (windows_os()) {
            $file = str_replace('/', '\\', $file);
        }
        $this->components->info(sprintf('Migration [%s] created successfully.', $file));
    }
    /**
     * Get migration path (either specified by '--path' option or default location).
     */
    protected function get_migration_path(): string
    {
        if (!is_null($target_path = $this->input->get_option('path'))) {
            return !$this->using_real_path() ? $this->laravel->base_path() . '/' . $target_path : $target_path;
        }
        return parent::get_migration_path();
    }
    /**
     * Prompt for missing input arguments using the returned questions.
     */
    protected function prompt_for_missing_arguments_using(): array
    {
        return ['name' => ['What should the migration be named?', 'E.g. create_flights_table']];
    }
}