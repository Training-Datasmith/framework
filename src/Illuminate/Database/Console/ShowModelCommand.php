<?php

declare (strict_types=1);
namespace Illuminate\Database\Console;

use Illuminate\Console\Concerns\Finds_Available_Models;
use Illuminate\Contracts\Console\Prompts_For_Missing_Input;
use Illuminate\Contracts\Container\Binding_Resolution_Exception;
use Illuminate\Database\Eloquent\Model_Inspector;
use Illuminate\Support\Collection;
use function Laravel\Prompts\suggest;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Output\Output_Interface;
#[As_Command(name: 'model:show')]
class Show_Model_Command extends Database_Inspection_Command implements Prompts_For_Missing_Input
{
    use Finds_Available_Models;
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'model:show {model}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show information about an Eloquent model';
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'model:show {model : The model to show}
                {--database= : The database connection to use}
                {--json : Output the model as JSON}';
    /**
     * Execute the console command.
     */
    public function handle(Model_Inspector $model_inspector): int
    {
        try {
            $info = $model_inspector->inspect($this->argument('model'), $this->option('database'));
        } catch (Binding_Resolution_Exception $e) {
            $this->components->error($e->get_message());
            return 1;
        }
        $this->display($info['class'], $info['database'], $info['table'], $info['policy'], $info['attributes'], $info['relations'], $info['events'], $info['observers']);
        return 0;
    }
    /**
     * Render the model information.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $class
     * @param  string  $database
     * @param  string  $table
     * @param  class-string|null  $policy
     * @param  \Illuminate\Support\Collection  $attributes
     * @param  \Illuminate\Support\Collection  $relations
     * @param  \Illuminate\Support\Collection  $events
     * @param  \Illuminate\Support\Collection  $observers
     * @return void
     */
    protected function display($class, $database, $table, $policy, $attributes, $relations, $events, $observers)
    {
        $this->option('json') ? $this->display_json($class, $database, $table, $policy, $attributes, $relations, $events, $observers) : $this->display_cli($class, $database, $table, $policy, $attributes, $relations, $events, $observers);
    }
    /**
     * Render the model information as JSON.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $class
     * @param  string  $database
     * @param  string  $table
     * @param  class-string|null  $policy
     * @param  \Illuminate\Support\Collection  $attributes
     * @param  \Illuminate\Support\Collection  $relations
     * @param  \Illuminate\Support\Collection  $events
     * @param  \Illuminate\Support\Collection  $observers
     * @return void
     */
    protected function display_json($class, $database, $table, $policy, $attributes, $relations, $events, $observers)
    {
        $this->output->writeln((new Collection(['class' => $class, 'database' => $database, 'table' => $table, 'policy' => $policy, 'attributes' => $attributes, 'relations' => $relations, 'events' => $events, 'observers' => $observers]))->to_json());
    }
    /**
     * Render the model information for the CLI.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $class
     * @param  string  $database
     * @param  string  $table
     * @param  class-string|null  $policy
     * @param  \Illuminate\Support\Collection  $attributes
     * @param  \Illuminate\Support\Collection  $relations
     * @param  \Illuminate\Support\Collection  $events
     * @param  \Illuminate\Support\Collection  $observers
     * @return void
     */
    protected function display_cli(string $class, $database, $table, $policy, $attributes, $relations, $events, $observers)
    {
        $this->new_line();
        $this->components->two_column_detail('<fg=green;options=bold>' . $class . '</>');
        $this->components->two_column_detail('Database', $database);
        $this->components->two_column_detail('Table', $table);
        if ($policy) {
            $this->components->two_column_detail('Policy', $policy);
        }
        $this->new_line();
        $this->components->two_column_detail('<fg=green;options=bold>Attributes</>', 'type <fg=gray>/</> <fg=yellow;options=bold>cast</>');
        foreach ($attributes as $attribute) {
            $first = trim(sprintf('%s %s', $attribute['name'], (new Collection(['increments', 'unique', 'nullable', 'fillable', 'hidden', 'appended']))->filter(fn($property) => $attribute[$property])->map(fn(string $property): string => sprintf('<fg=gray>%s</>', $property))->implode('<fg=gray>,</> ')));
            $second = (new Collection([$attribute['type'], $attribute['cast'] ? '<fg=yellow;options=bold>' . $attribute['cast'] . '</>' : null]))->filter()->implode(' <fg=gray>/</> ');
            $this->components->two_column_detail($first, $second);
            if ($attribute['default'] !== null) {
                $this->components->bullet_list([sprintf('default: %s', $attribute['default'])], Output_Interface::VERBOSITY_VERBOSE);
            }
        }
        $this->new_line();
        $this->components->two_column_detail('<fg=green;options=bold>Relations</>');
        foreach ($relations as $relation) {
            $this->components->two_column_detail(sprintf('%s <fg=gray>%s</>', $relation['name'], $relation['type']), $relation['related']);
        }
        $this->new_line();
        $this->components->two_column_detail('<fg=green;options=bold>Events</>');
        if ($events->count()) {
            foreach ($events as $event) {
                $this->components->two_column_detail(sprintf('%s', $event['event']), sprintf('%s', $event['class']));
            }
        }
        $this->new_line();
        $this->components->two_column_detail('<fg=green;options=bold>Observers</>');
        if ($observers->count()) {
            foreach ($observers as $observer) {
                $this->components->two_column_detail(sprintf('%s', $observer['event']), implode(', ', $observer['observer']));
            }
        }
        $this->new_line();
    }
    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array<string, \Closure(): string>
     */
    protected function prompt_for_missing_arguments_using(): array
    {
        return ['model' => fn(): string => suggest('Which model would you like to show?', $this->find_available_models())];
    }
}