<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Providers;

use Illuminate\Auth\Console\Clear_Resets_Command;
use Illuminate\Cache\Console\Cache_Table_Command;
use Illuminate\Cache\Console\Clear_Command as CacheClearCommand;
use Illuminate\Cache\Console\Forget_Command as CacheForgetCommand;
use Illuminate\Cache\Console\Prune_Stale_Tags_Command;
use Illuminate\Concurrency\Console\Invoke_Serialized_Closure_Command;
use Illuminate\Console\Scheduling\Schedule_Clear_Cache_Command;
use Illuminate\Console\Scheduling\Schedule_Finish_Command;
use Illuminate\Console\Scheduling\Schedule_Interrupt_Command;
use Illuminate\Console\Scheduling\Schedule_List_Command;
use Illuminate\Console\Scheduling\Schedule_Run_Command;
use Illuminate\Console\Scheduling\Schedule_Test_Command;
use Illuminate\Console\Scheduling\Schedule_Work_Command;
use Illuminate\Console\Signals;
use Illuminate\Contracts\Support\Deferrable_Provider;
use Illuminate\Database\Console\Db_Command;
use Illuminate\Database\Console\Dump_Command;
use Illuminate\Database\Console\Factories\Factory_Make_Command;
use Illuminate\Database\Console\Monitor_Command as DatabaseMonitorCommand;
use Illuminate\Database\Console\Prune_Command;
use Illuminate\Database\Console\Seeds\Seed_Command;
use Illuminate\Database\Console\Seeds\Seeder_Make_Command;
use Illuminate\Database\Console\Show_Command;
use Illuminate\Database\Console\Show_Model_Command;
use Illuminate\Database\Console\Table_Command as DatabaseTableCommand;
use Illuminate\Database\Console\Wipe_Command;
use Illuminate\Foundation\Console\About_Command;
use Illuminate\Foundation\Console\Api_Install_Command;
use Illuminate\Foundation\Console\Broadcasting_Install_Command;
use Illuminate\Foundation\Console\Cast_Make_Command;
use Illuminate\Foundation\Console\Channel_List_Command;
use Illuminate\Foundation\Console\Channel_Make_Command;
use Illuminate\Foundation\Console\Class_Make_Command;
use Illuminate\Foundation\Console\Clear_Compiled_Command;
use Illuminate\Foundation\Console\Component_Make_Command;
use Illuminate\Foundation\Console\Config_Cache_Command;
use Illuminate\Foundation\Console\Config_Clear_Command;
use Illuminate\Foundation\Console\Config_Make_Command;
use Illuminate\Foundation\Console\Config_Publish_Command;
use Illuminate\Foundation\Console\Config_Show_Command;
use Illuminate\Foundation\Console\Console_Make_Command;
use Illuminate\Foundation\Console\Docs_Command;
use Illuminate\Foundation\Console\Down_Command;
use Illuminate\Foundation\Console\Enum_Make_Command;
use Illuminate\Foundation\Console\Environment_Command;
use Illuminate\Foundation\Console\Environment_Decrypt_Command;
use Illuminate\Foundation\Console\Environment_Encrypt_Command;
use Illuminate\Foundation\Console\Event_Cache_Command;
use Illuminate\Foundation\Console\Event_Clear_Command;
use Illuminate\Foundation\Console\Event_Generate_Command;
use Illuminate\Foundation\Console\Event_List_Command;
use Illuminate\Foundation\Console\Event_Make_Command;
use Illuminate\Foundation\Console\Exception_Make_Command;
use Illuminate\Foundation\Console\Interface_Make_Command;
use Illuminate\Foundation\Console\Job_Make_Command;
use Illuminate\Foundation\Console\Job_Middleware_Make_Command;
use Illuminate\Foundation\Console\Key_Generate_Command;
use Illuminate\Foundation\Console\Lang_Publish_Command;
use Illuminate\Foundation\Console\Listener_Make_Command;
use Illuminate\Foundation\Console\Mail_Make_Command;
use Illuminate\Foundation\Console\Model_Make_Command;
use Illuminate\Foundation\Console\Notification_Make_Command;
use Illuminate\Foundation\Console\Observer_Make_Command;
use Illuminate\Foundation\Console\Optimize_Clear_Command;
use Illuminate\Foundation\Console\Optimize_Command;
use Illuminate\Foundation\Console\Package_Discover_Command;
use Illuminate\Foundation\Console\Policy_Make_Command;
use Illuminate\Foundation\Console\Provider_Make_Command;
use Illuminate\Foundation\Console\Reload_Command;
use Illuminate\Foundation\Console\Request_Make_Command;
use Illuminate\Foundation\Console\Resource_Make_Command;
use Illuminate\Foundation\Console\Route_Cache_Command;
use Illuminate\Foundation\Console\Route_Clear_Command;
use Illuminate\Foundation\Console\Route_List_Command;
use Illuminate\Foundation\Console\Rule_Make_Command;
use Illuminate\Foundation\Console\Scope_Make_Command;
use Illuminate\Foundation\Console\Serve_Command;
use Illuminate\Foundation\Console\Storage_Link_Command;
use Illuminate\Foundation\Console\Storage_Unlink_Command;
use Illuminate\Foundation\Console\Stub_Publish_Command;
use Illuminate\Foundation\Console\Test_Make_Command;
use Illuminate\Foundation\Console\Trait_Make_Command;
use Illuminate\Foundation\Console\Up_Command;
use Illuminate\Foundation\Console\Vendor_Publish_Command;
use Illuminate\Foundation\Console\View_Cache_Command;
use Illuminate\Foundation\Console\View_Clear_Command;
use Illuminate\Foundation\Console\View_Make_Command;
use Illuminate\Notifications\Console\Notification_Table_Command;
use Illuminate\Queue\Console\Batches_Table_Command;
use Illuminate\Queue\Console\Clear_Command as QueueClearCommand;
use Illuminate\Queue\Console\Failed_Table_Command;
use Illuminate\Queue\Console\Flush_Failed_Command as FlushFailedQueueCommand;
use Illuminate\Queue\Console\Forget_Failed_Command as ForgetFailedQueueCommand;
use Illuminate\Queue\Console\Listen_Command as QueueListenCommand;
use Illuminate\Queue\Console\List_Failed_Command as ListFailedQueueCommand;
use Illuminate\Queue\Console\Monitor_Command as QueueMonitorCommand;
use Illuminate\Queue\Console\Pause_Command as QueuePauseCommand;
use Illuminate\Queue\Console\Prune_Batches_Command as QueuePruneBatchesCommand;
use Illuminate\Queue\Console\Prune_Failed_Jobs_Command as QueuePruneFailedJobsCommand;
use Illuminate\Queue\Console\Restart_Command as QueueRestartCommand;
use Illuminate\Queue\Console\Resume_Command as QueueResumeCommand;
use Illuminate\Queue\Console\Retry_Batch_Command as QueueRetryBatchCommand;
use Illuminate\Queue\Console\Retry_Command as QueueRetryCommand;
use Illuminate\Queue\Console\Table_Command;
use Illuminate\Queue\Console\Work_Command as QueueWorkCommand;
use Illuminate\Routing\Console\Controller_Make_Command;
use Illuminate\Routing\Console\Middleware_Make_Command;
use Illuminate\Session\Console\Session_Table_Command;
use Illuminate\Support\Service_Provider;
class Artisan_Service_Provider extends Service_Provider implements Deferrable_Provider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = ['About' => About_Command::class, 'CacheClear' => Cache_Clear_Command::class, 'CacheForget' => Cache_Forget_Command::class, 'ClearCompiled' => Clear_Compiled_Command::class, 'ClearResets' => Clear_Resets_Command::class, 'ConfigCache' => Config_Cache_Command::class, 'ConfigClear' => Config_Clear_Command::class, 'ConfigShow' => Config_Show_Command::class, 'Db' => Db_Command::class, 'DbMonitor' => Database_Monitor_Command::class, 'DbPrune' => Prune_Command::class, 'DbShow' => Show_Command::class, 'DbTable' => Database_Table_Command::class, 'DbWipe' => Wipe_Command::class, 'Down' => Down_Command::class, 'Environment' => Environment_Command::class, 'EnvironmentDecrypt' => Environment_Decrypt_Command::class, 'EnvironmentEncrypt' => Environment_Encrypt_Command::class, 'EventCache' => Event_Cache_Command::class, 'EventClear' => Event_Clear_Command::class, 'EventList' => Event_List_Command::class, 'InvokeSerializedClosure' => Invoke_Serialized_Closure_Command::class, 'KeyGenerate' => Key_Generate_Command::class, 'Optimize' => Optimize_Command::class, 'OptimizeClear' => Optimize_Clear_Command::class, 'PackageDiscover' => Package_Discover_Command::class, 'PruneStaleTagsCommand' => Prune_Stale_Tags_Command::class, 'QueueClear' => Queue_Clear_Command::class, 'QueueFailed' => List_Failed_Queue_Command::class, 'QueueFlush' => Flush_Failed_Queue_Command::class, 'QueueForget' => Forget_Failed_Queue_Command::class, 'QueueListen' => Queue_Listen_Command::class, 'QueueMonitor' => Queue_Monitor_Command::class, 'QueuePause' => Queue_Pause_Command::class, 'QueuePruneBatches' => Queue_Prune_Batches_Command::class, 'QueuePruneFailedJobs' => Queue_Prune_Failed_Jobs_Command::class, 'QueueRestart' => Queue_Restart_Command::class, 'QueueResume' => Queue_Resume_Command::class, 'QueueRetry' => Queue_Retry_Command::class, 'QueueRetryBatch' => Queue_Retry_Batch_Command::class, 'QueueWork' => Queue_Work_Command::class, 'Reload' => Reload_Command::class, 'RouteCache' => Route_Cache_Command::class, 'RouteClear' => Route_Clear_Command::class, 'RouteList' => Route_List_Command::class, 'SchemaDump' => Dump_Command::class, 'Seed' => Seed_Command::class, 'ScheduleFinish' => Schedule_Finish_Command::class, 'ScheduleList' => Schedule_List_Command::class, 'ScheduleRun' => Schedule_Run_Command::class, 'ScheduleClearCache' => Schedule_Clear_Cache_Command::class, 'ScheduleTest' => Schedule_Test_Command::class, 'ScheduleWork' => Schedule_Work_Command::class, 'ScheduleInterrupt' => Schedule_Interrupt_Command::class, 'ShowModel' => Show_Model_Command::class, 'StorageLink' => Storage_Link_Command::class, 'StorageUnlink' => Storage_Unlink_Command::class, 'Up' => Up_Command::class, 'ViewCache' => View_Cache_Command::class, 'ViewClear' => View_Clear_Command::class];
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $dev_commands = ['ApiInstall' => Api_Install_Command::class, 'BroadcastingInstall' => Broadcasting_Install_Command::class, 'CacheTable' => Cache_Table_Command::class, 'CastMake' => Cast_Make_Command::class, 'ChannelList' => Channel_List_Command::class, 'ChannelMake' => Channel_Make_Command::class, 'ClassMake' => Class_Make_Command::class, 'ComponentMake' => Component_Make_Command::class, 'ConfigMake' => Config_Make_Command::class, 'ConfigPublish' => Config_Publish_Command::class, 'ConsoleMake' => Console_Make_Command::class, 'ControllerMake' => Controller_Make_Command::class, 'Docs' => Docs_Command::class, 'EnumMake' => Enum_Make_Command::class, 'EventGenerate' => Event_Generate_Command::class, 'EventMake' => Event_Make_Command::class, 'ExceptionMake' => Exception_Make_Command::class, 'FactoryMake' => Factory_Make_Command::class, 'InterfaceMake' => Interface_Make_Command::class, 'JobMake' => Job_Make_Command::class, 'JobMiddlewareMake' => Job_Middleware_Make_Command::class, 'LangPublish' => Lang_Publish_Command::class, 'ListenerMake' => Listener_Make_Command::class, 'MailMake' => Mail_Make_Command::class, 'MiddlewareMake' => Middleware_Make_Command::class, 'ModelMake' => Model_Make_Command::class, 'NotificationMake' => Notification_Make_Command::class, 'NotificationTable' => Notification_Table_Command::class, 'ObserverMake' => Observer_Make_Command::class, 'PolicyMake' => Policy_Make_Command::class, 'ProviderMake' => Provider_Make_Command::class, 'QueueFailedTable' => Failed_Table_Command::class, 'QueueTable' => Table_Command::class, 'QueueBatchesTable' => Batches_Table_Command::class, 'RequestMake' => Request_Make_Command::class, 'ResourceMake' => Resource_Make_Command::class, 'RuleMake' => Rule_Make_Command::class, 'ScopeMake' => Scope_Make_Command::class, 'SeederMake' => Seeder_Make_Command::class, 'SessionTable' => Session_Table_Command::class, 'Serve' => Serve_Command::class, 'StubPublish' => Stub_Publish_Command::class, 'TestMake' => Test_Make_Command::class, 'TraitMake' => Trait_Make_Command::class, 'VendorPublish' => Vendor_Publish_Command::class, 'ViewMake' => View_Make_Command::class];
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->register_commands(array_merge($this->commands, $this->dev_commands));
        Signals::resolve_availability_using(fn(): bool => $this->app->running_in_console() && !$this->app->running_unit_tests() && extension_loaded('pcntl'));
    }
    /**
     * Register the given commands.
     *
     * @return void
     */
    protected function register_commands(array $commands)
    {
        foreach ($commands as $command_name => $command) {
            $method = "register{$command_name}Command";
            if (method_exists($this, $method)) {
                $this->{$method}();
            } else {
                $this->app->singleton($command);
            }
        }
        $this->commands(array_values($commands));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_about_command()
    {
        $this->app->singleton(About_Command::class, fn($app): \Illuminate\Foundation\Console\About_Command => new About_Command($app['composer']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_cache_clear_command()
    {
        $this->app->singleton(Cache_Clear_Command::class, fn($app): \Illuminate\Cache\Console\Clear_Command => new Cache_Clear_Command($app['cache'], $app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_cache_forget_command()
    {
        $this->app->singleton(Cache_Forget_Command::class, fn($app): \Illuminate\Cache\Console\Forget_Command => new Cache_Forget_Command($app['cache']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_cache_table_command()
    {
        $this->app->singleton(Cache_Table_Command::class, fn($app): \Illuminate\Cache\Console\Cache_Table_Command => new Cache_Table_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_cast_make_command()
    {
        $this->app->singleton(Cast_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Cast_Make_Command => new Cast_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_channel_make_command()
    {
        $this->app->singleton(Channel_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Channel_Make_Command => new Channel_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_class_make_command()
    {
        $this->app->singleton(Class_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Class_Make_Command => new Class_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_component_make_command()
    {
        $this->app->singleton(Component_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Component_Make_Command => new Component_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_config_cache_command()
    {
        $this->app->singleton(Config_Cache_Command::class, fn($app): \Illuminate\Foundation\Console\Config_Cache_Command => new Config_Cache_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_config_clear_command()
    {
        $this->app->singleton(Config_Clear_Command::class, fn($app): \Illuminate\Foundation\Console\Config_Clear_Command => new Config_Clear_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_config_make_command()
    {
        $this->app->singleton(Config_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Config_Make_Command => new Config_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_config_publish_command()
    {
        $this->app->singleton(Config_Publish_Command::class, fn(): \Illuminate\Foundation\Console\Config_Publish_Command => new Config_Publish_Command());
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_console_make_command()
    {
        $this->app->singleton(Console_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Console_Make_Command => new Console_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_controller_make_command()
    {
        $this->app->singleton(Controller_Make_Command::class, fn($app): \Illuminate\Routing\Console\Controller_Make_Command => new Controller_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_enum_make_command()
    {
        $this->app->singleton(Enum_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Enum_Make_Command => new Enum_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_event_make_command()
    {
        $this->app->singleton(Event_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Event_Make_Command => new Event_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_exception_make_command()
    {
        $this->app->singleton(Exception_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Exception_Make_Command => new Exception_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_factory_make_command()
    {
        $this->app->singleton(Factory_Make_Command::class, fn($app): \Illuminate\Database\Console\Factories\Factory_Make_Command => new Factory_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_event_clear_command()
    {
        $this->app->singleton(Event_Clear_Command::class, fn($app): \Illuminate\Foundation\Console\Event_Clear_Command => new Event_Clear_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_interface_make_command()
    {
        $this->app->singleton(Interface_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Interface_Make_Command => new Interface_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_job_make_command()
    {
        $this->app->singleton(Job_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Job_Make_Command => new Job_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_job_middleware_make_command()
    {
        $this->app->singleton(Job_Middleware_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Job_Middleware_Make_Command => new Job_Middleware_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_listener_make_command()
    {
        $this->app->singleton(Listener_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Listener_Make_Command => new Listener_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_mail_make_command()
    {
        $this->app->singleton(Mail_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Mail_Make_Command => new Mail_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_middleware_make_command()
    {
        $this->app->singleton(Middleware_Make_Command::class, fn($app): \Illuminate\Routing\Console\Middleware_Make_Command => new Middleware_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_model_make_command()
    {
        $this->app->singleton(Model_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Model_Make_Command => new Model_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_notification_make_command()
    {
        $this->app->singleton(Notification_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Notification_Make_Command => new Notification_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_notification_table_command()
    {
        $this->app->singleton(Notification_Table_Command::class, fn($app): \Illuminate\Notifications\Console\Notification_Table_Command => new Notification_Table_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_observer_make_command()
    {
        $this->app->singleton(Observer_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Observer_Make_Command => new Observer_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_policy_make_command()
    {
        $this->app->singleton(Policy_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Policy_Make_Command => new Policy_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_provider_make_command()
    {
        $this->app->singleton(Provider_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Provider_Make_Command => new Provider_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_forget_command()
    {
        $this->app->singleton(Forget_Failed_Queue_Command::class);
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_listen_command()
    {
        $this->app->singleton(Queue_Listen_Command::class, fn($app): \Illuminate\Queue\Console\Listen_Command => new Queue_Listen_Command($app['queue.listener']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_monitor_command()
    {
        $this->app->singleton(Queue_Monitor_Command::class, fn($app): \Illuminate\Queue\Console\Monitor_Command => new Queue_Monitor_Command($app['queue'], $app['events']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_prune_batches_command()
    {
        $this->app->singleton(Queue_Prune_Batches_Command::class, fn(): \Illuminate\Queue\Console\Prune_Batches_Command => new Queue_Prune_Batches_Command());
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_prune_failed_jobs_command()
    {
        $this->app->singleton(Queue_Prune_Failed_Jobs_Command::class, fn(): \Illuminate\Queue\Console\Prune_Failed_Jobs_Command => new Queue_Prune_Failed_Jobs_Command());
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_restart_command()
    {
        $this->app->singleton(Queue_Restart_Command::class, fn($app): \Illuminate\Queue\Console\Restart_Command => new Queue_Restart_Command($app['cache.store']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_work_command()
    {
        $this->app->singleton(Queue_Work_Command::class, fn($app): \Illuminate\Queue\Console\Work_Command => new Queue_Work_Command($app['queue.worker'], $app['cache.store']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_failed_table_command()
    {
        $this->app->singleton(Failed_Table_Command::class, fn($app): \Illuminate\Queue\Console\Failed_Table_Command => new Failed_Table_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_table_command()
    {
        $this->app->singleton(Table_Command::class, fn($app): \Illuminate\Queue\Console\Table_Command => new Table_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_queue_batches_table_command()
    {
        $this->app->singleton(Batches_Table_Command::class, fn($app): \Illuminate\Queue\Console\Batches_Table_Command => new Batches_Table_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_request_make_command()
    {
        $this->app->singleton(Request_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Request_Make_Command => new Request_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_resource_make_command()
    {
        $this->app->singleton(Resource_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Resource_Make_Command => new Resource_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_rule_make_command()
    {
        $this->app->singleton(Rule_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Rule_Make_Command => new Rule_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_scope_make_command()
    {
        $this->app->singleton(Scope_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Scope_Make_Command => new Scope_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_seeder_make_command()
    {
        $this->app->singleton(Seeder_Make_Command::class, fn($app): \Illuminate\Database\Console\Seeds\Seeder_Make_Command => new Seeder_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_session_table_command()
    {
        $this->app->singleton(Session_Table_Command::class, fn($app): \Illuminate\Session\Console\Session_Table_Command => new Session_Table_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_route_cache_command()
    {
        $this->app->singleton(Route_Cache_Command::class, fn($app): \Illuminate\Foundation\Console\Route_Cache_Command => new Route_Cache_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_route_clear_command()
    {
        $this->app->singleton(Route_Clear_Command::class, fn($app): \Illuminate\Foundation\Console\Route_Clear_Command => new Route_Clear_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_route_list_command()
    {
        $this->app->singleton(Route_List_Command::class, fn($app): \Illuminate\Foundation\Console\Route_List_Command => new Route_List_Command($app['router']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_seed_command()
    {
        $this->app->singleton(Seed_Command::class, fn($app): \Illuminate\Database\Console\Seeds\Seed_Command => new Seed_Command($app['db']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_test_make_command()
    {
        $this->app->singleton(Test_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Test_Make_Command => new Test_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_trait_make_command()
    {
        $this->app->singleton(Trait_Make_Command::class, fn($app): \Illuminate\Foundation\Console\Trait_Make_Command => new Trait_Make_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_vendor_publish_command()
    {
        $this->app->singleton(Vendor_Publish_Command::class, fn($app): \Illuminate\Foundation\Console\Vendor_Publish_Command => new Vendor_Publish_Command($app['files']));
    }
    /**
     * Register the command.
     *
     * @return void
     */
    protected function register_view_clear_command()
    {
        $this->app->singleton(View_Clear_Command::class, fn($app): \Illuminate\Foundation\Console\View_Clear_Command => new View_Clear_Command($app['files']));
    }
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return array_merge(array_values($this->commands), array_values($this->dev_commands));
    }
}