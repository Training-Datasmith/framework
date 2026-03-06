<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Providers;

use Illuminate\Auth\Console\ClearResetsCommand;
use Illuminate\Cache\Console\CacheTableCommand;
use Illuminate\Cache\Console\ClearCommand as CacheClearCommand;
use Illuminate\Cache\Console\ForgetCommand as CacheForgetCommand;
use Illuminate\Cache\Console\PruneStaleTagsCommand;
use Illuminate\Concurrency\Console\InvokeSerializedClosureCommand;
use Illuminate\Console\Scheduling\ScheduleClearCacheCommand;
use Illuminate\Console\Scheduling\ScheduleFinishCommand;
use Illuminate\Console\Scheduling\ScheduleInterruptCommand;
use Illuminate\Console\Scheduling\ScheduleListCommand;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Console\Scheduling\ScheduleTestCommand;
use Illuminate\Console\Scheduling\ScheduleWorkCommand;
use Illuminate\Console\Signals;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Database\Console\DbCommand;
use Illuminate\Database\Console\DumpCommand;
use Illuminate\Database\Console\Factories\FactoryMakeCommand;
use Illuminate\Database\Console\MonitorCommand as DatabaseMonitorCommand;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\Console\Seeds\SeederMakeCommand;
use Illuminate\Database\Console\ShowCommand;
use Illuminate\Database\Console\ShowModelCommand;
use Illuminate\Database\Console\TableCommand as DatabaseTableCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Console\ApiInstallCommand;
use Illuminate\Foundation\Console\BroadcastingInstallCommand;
use Illuminate\Foundation\Console\CastMakeCommand;
use Illuminate\Foundation\Console\ChannelListCommand;
use Illuminate\Foundation\Console\ChannelMakeCommand;
use Illuminate\Foundation\Console\ClassMakeCommand;
use Illuminate\Foundation\Console\ClearCompiledCommand;
use Illuminate\Foundation\Console\ComponentMakeCommand;
use Illuminate\Foundation\Console\ConfigCacheCommand;
use Illuminate\Foundation\Console\ConfigClearCommand;
use Illuminate\Foundation\Console\ConfigMakeCommand;
use Illuminate\Foundation\Console\ConfigPublishCommand;
use Illuminate\Foundation\Console\ConfigShowCommand;
use Illuminate\Foundation\Console\ConsoleMakeCommand;
use Illuminate\Foundation\Console\DocsCommand;
use Illuminate\Foundation\Console\DownCommand;
use Illuminate\Foundation\Console\EnumMakeCommand;
use Illuminate\Foundation\Console\EnvironmentCommand;
use Illuminate\Foundation\Console\EnvironmentDecryptCommand;
use Illuminate\Foundation\Console\EnvironmentEncryptCommand;
use Illuminate\Foundation\Console\EventCacheCommand;
use Illuminate\Foundation\Console\EventClearCommand;
use Illuminate\Foundation\Console\EventGenerateCommand;
use Illuminate\Foundation\Console\EventListCommand;
use Illuminate\Foundation\Console\EventMakeCommand;
use Illuminate\Foundation\Console\ExceptionMakeCommand;
use Illuminate\Foundation\Console\InterfaceMakeCommand;
use Illuminate\Foundation\Console\JobMakeCommand;
use Illuminate\Foundation\Console\JobMiddlewareMakeCommand;
use Illuminate\Foundation\Console\KeyGenerateCommand;
use Illuminate\Foundation\Console\LangPublishCommand;
use Illuminate\Foundation\Console\ListenerMakeCommand;
use Illuminate\Foundation\Console\MailMakeCommand;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Foundation\Console\NotificationMakeCommand;
use Illuminate\Foundation\Console\ObserverMakeCommand;
use Illuminate\Foundation\Console\OptimizeClearCommand;
use Illuminate\Foundation\Console\OptimizeCommand;
use Illuminate\Foundation\Console\PackageDiscoverCommand;
use Illuminate\Foundation\Console\PolicyMakeCommand;
use Illuminate\Foundation\Console\ProviderMakeCommand;
use Illuminate\Foundation\Console\ReloadCommand;
use Illuminate\Foundation\Console\RequestMakeCommand;
use Illuminate\Foundation\Console\ResourceMakeCommand;
use Illuminate\Foundation\Console\RouteCacheCommand;
use Illuminate\Foundation\Console\RouteClearCommand;
use Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Foundation\Console\RuleMakeCommand;
use Illuminate\Foundation\Console\ScopeMakeCommand;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Foundation\Console\StorageLinkCommand;
use Illuminate\Foundation\Console\StorageUnlinkCommand;
use Illuminate\Foundation\Console\StubPublishCommand;
use Illuminate\Foundation\Console\TestMakeCommand;
use Illuminate\Foundation\Console\TraitMakeCommand;
use Illuminate\Foundation\Console\UpCommand;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Illuminate\Foundation\Console\ViewCacheCommand;
use Illuminate\Foundation\Console\ViewClearCommand;
use Illuminate\Foundation\Console\ViewMakeCommand;
use Illuminate\Notifications\Console\NotificationTableCommand;
use Illuminate\Queue\Console\BatchesTableCommand;
use Illuminate\Queue\Console\ClearCommand as QueueClearCommand;
use Illuminate\Queue\Console\FailedTableCommand;
use Illuminate\Queue\Console\FlushFailedCommand as FlushFailedQueueCommand;
use Illuminate\Queue\Console\ForgetFailedCommand as ForgetFailedQueueCommand;
use Illuminate\Queue\Console\ListenCommand as QueueListenCommand;
use Illuminate\Queue\Console\ListFailedCommand as ListFailedQueueCommand;
use Illuminate\Queue\Console\MonitorCommand as QueueMonitorCommand;
use Illuminate\Queue\Console\PauseCommand as QueuePauseCommand;
use Illuminate\Queue\Console\PruneBatchesCommand as QueuePruneBatchesCommand;
use Illuminate\Queue\Console\PruneFailedJobsCommand as QueuePruneFailedJobsCommand;
use Illuminate\Queue\Console\RestartCommand as QueueRestartCommand;
use Illuminate\Queue\Console\ResumeCommand as QueueResumeCommand;
use Illuminate\Queue\Console\RetryBatchCommand as QueueRetryBatchCommand;
use Illuminate\Queue\Console\RetryCommand as QueueRetryCommand;
use Illuminate\Queue\Console\TableCommand;
use Illuminate\Queue\Console\WorkCommand as QueueWorkCommand;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Routing\Console\MiddlewareMakeCommand;
use Illuminate\Session\Console\SessionTableCommand;
use Illuminate\Support\ServiceProvider;

class ArtisanServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        'About' => AboutCommand::class,
        'CacheClear' => CacheClearCommand::class,
        'CacheForget' => CacheForgetCommand::class,
        'ClearCompiled' => ClearCompiledCommand::class,
        'ClearResets' => ClearResetsCommand::class,
        'ConfigCache' => ConfigCacheCommand::class,
        'ConfigClear' => ConfigClearCommand::class,
        'ConfigShow' => ConfigShowCommand::class,
        'Db' => DbCommand::class,
        'DbMonitor' => DatabaseMonitorCommand::class,
        'DbPrune' => PruneCommand::class,
        'DbShow' => ShowCommand::class,
        'DbTable' => DatabaseTableCommand::class,
        'DbWipe' => WipeCommand::class,
        'Down' => DownCommand::class,
        'Environment' => EnvironmentCommand::class,
        'EnvironmentDecrypt' => EnvironmentDecryptCommand::class,
        'EnvironmentEncrypt' => EnvironmentEncryptCommand::class,
        'EventCache' => EventCacheCommand::class,
        'EventClear' => EventClearCommand::class,
        'EventList' => EventListCommand::class,
        'InvokeSerializedClosure' => InvokeSerializedClosureCommand::class,
        'KeyGenerate' => KeyGenerateCommand::class,
        'Optimize' => OptimizeCommand::class,
        'OptimizeClear' => OptimizeClearCommand::class,
        'PackageDiscover' => PackageDiscoverCommand::class,
        'PruneStaleTagsCommand' => PruneStaleTagsCommand::class,
        'QueueClear' => QueueClearCommand::class,
        'QueueFailed' => ListFailedQueueCommand::class,
        'QueueFlush' => FlushFailedQueueCommand::class,
        'QueueForget' => ForgetFailedQueueCommand::class,
        'QueueListen' => QueueListenCommand::class,
        'QueueMonitor' => QueueMonitorCommand::class,
        'QueuePause' => QueuePauseCommand::class,
        'QueuePruneBatches' => QueuePruneBatchesCommand::class,
        'QueuePruneFailedJobs' => QueuePruneFailedJobsCommand::class,
        'QueueRestart' => QueueRestartCommand::class,
        'QueueResume' => QueueResumeCommand::class,
        'QueueRetry' => QueueRetryCommand::class,
        'QueueRetryBatch' => QueueRetryBatchCommand::class,
        'QueueWork' => QueueWorkCommand::class,
        'Reload' => ReloadCommand::class,
        'RouteCache' => RouteCacheCommand::class,
        'RouteClear' => RouteClearCommand::class,
        'RouteList' => RouteListCommand::class,
        'SchemaDump' => DumpCommand::class,
        'Seed' => SeedCommand::class,
        'ScheduleFinish' => ScheduleFinishCommand::class,
        'ScheduleList' => ScheduleListCommand::class,
        'ScheduleRun' => ScheduleRunCommand::class,
        'ScheduleClearCache' => ScheduleClearCacheCommand::class,
        'ScheduleTest' => ScheduleTestCommand::class,
        'ScheduleWork' => ScheduleWorkCommand::class,
        'ScheduleInterrupt' => ScheduleInterruptCommand::class,
        'ShowModel' => ShowModelCommand::class,
        'StorageLink' => StorageLinkCommand::class,
        'StorageUnlink' => StorageUnlinkCommand::class,
        'Up' => UpCommand::class,
        'ViewCache' => ViewCacheCommand::class,
        'ViewClear' => ViewClearCommand::class,
    ];

    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $devCommands = [
        'ApiInstall' => ApiInstallCommand::class,
        'BroadcastingInstall' => BroadcastingInstallCommand::class,
        'CacheTable' => CacheTableCommand::class,
        'CastMake' => CastMakeCommand::class,
        'ChannelList' => ChannelListCommand::class,
        'ChannelMake' => ChannelMakeCommand::class,
        'ClassMake' => ClassMakeCommand::class,
        'ComponentMake' => ComponentMakeCommand::class,
        'ConfigMake' => ConfigMakeCommand::class,
        'ConfigPublish' => ConfigPublishCommand::class,
        'ConsoleMake' => ConsoleMakeCommand::class,
        'ControllerMake' => ControllerMakeCommand::class,
        'Docs' => DocsCommand::class,
        'EnumMake' => EnumMakeCommand::class,
        'EventGenerate' => EventGenerateCommand::class,
        'EventMake' => EventMakeCommand::class,
        'ExceptionMake' => ExceptionMakeCommand::class,
        'FactoryMake' => FactoryMakeCommand::class,
        'InterfaceMake' => InterfaceMakeCommand::class,
        'JobMake' => JobMakeCommand::class,
        'JobMiddlewareMake' => JobMiddlewareMakeCommand::class,
        'LangPublish' => LangPublishCommand::class,
        'ListenerMake' => ListenerMakeCommand::class,
        'MailMake' => MailMakeCommand::class,
        'MiddlewareMake' => MiddlewareMakeCommand::class,
        'ModelMake' => ModelMakeCommand::class,
        'NotificationMake' => NotificationMakeCommand::class,
        'NotificationTable' => NotificationTableCommand::class,
        'ObserverMake' => ObserverMakeCommand::class,
        'PolicyMake' => PolicyMakeCommand::class,
        'ProviderMake' => ProviderMakeCommand::class,
        'QueueFailedTable' => FailedTableCommand::class,
        'QueueTable' => TableCommand::class,
        'QueueBatchesTable' => BatchesTableCommand::class,
        'RequestMake' => RequestMakeCommand::class,
        'ResourceMake' => ResourceMakeCommand::class,
        'RuleMake' => RuleMakeCommand::class,
        'ScopeMake' => ScopeMakeCommand::class,
        'SeederMake' => SeederMakeCommand::class,
        'SessionTable' => SessionTableCommand::class,
        'Serve' => ServeCommand::class,
        'StubPublish' => StubPublishCommand::class,
        'TestMake' => TestMakeCommand::class,
        'TraitMake' => TraitMakeCommand::class,
        'VendorPublish' => VendorPublishCommand::class,
        'ViewMake' => ViewMakeCommand::class,
    ];

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->registerCommands(array_merge(
            $this->commands,
            $this->devCommands
        ));

        Signals::resolveAvailabilityUsing(fn (): bool => $this->app->runningInConsole()
            && ! $this->app->runningUnitTests()
            && extension_loaded('pcntl'));
    }

    /**
     * Register the given commands.
     *
     * @return void
     */
    protected function registerCommands(array $commands)
    {
        foreach ($commands as $commandName => $command) {
            $method = "register{$commandName}Command";

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
    protected function registerAboutCommand()
    {
        $this->app->singleton(AboutCommand::class, fn ($app): \Illuminate\Foundation\Console\AboutCommand => new AboutCommand($app['composer']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerCacheClearCommand()
    {
        $this->app->singleton(CacheClearCommand::class, fn ($app): \Illuminate\Cache\Console\ClearCommand => new CacheClearCommand($app['cache'], $app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerCacheForgetCommand()
    {
        $this->app->singleton(CacheForgetCommand::class, fn ($app): \Illuminate\Cache\Console\ForgetCommand => new CacheForgetCommand($app['cache']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerCacheTableCommand()
    {
        $this->app->singleton(CacheTableCommand::class, fn ($app): \Illuminate\Cache\Console\CacheTableCommand => new CacheTableCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerCastMakeCommand()
    {
        $this->app->singleton(CastMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\CastMakeCommand => new CastMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerChannelMakeCommand()
    {
        $this->app->singleton(ChannelMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ChannelMakeCommand => new ChannelMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerClassMakeCommand()
    {
        $this->app->singleton(ClassMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ClassMakeCommand => new ClassMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerComponentMakeCommand()
    {
        $this->app->singleton(ComponentMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ComponentMakeCommand => new ComponentMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerConfigCacheCommand()
    {
        $this->app->singleton(ConfigCacheCommand::class, fn ($app): \Illuminate\Foundation\Console\ConfigCacheCommand => new ConfigCacheCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerConfigClearCommand()
    {
        $this->app->singleton(ConfigClearCommand::class, fn ($app): \Illuminate\Foundation\Console\ConfigClearCommand => new ConfigClearCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerConfigMakeCommand()
    {
        $this->app->singleton(ConfigMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ConfigMakeCommand => new ConfigMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerConfigPublishCommand()
    {
        $this->app->singleton(ConfigPublishCommand::class, fn (): \Illuminate\Foundation\Console\ConfigPublishCommand => new ConfigPublishCommand());
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerConsoleMakeCommand()
    {
        $this->app->singleton(ConsoleMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ConsoleMakeCommand => new ConsoleMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerControllerMakeCommand()
    {
        $this->app->singleton(ControllerMakeCommand::class, fn ($app): \Illuminate\Routing\Console\ControllerMakeCommand => new ControllerMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerEnumMakeCommand()
    {
        $this->app->singleton(EnumMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\EnumMakeCommand => new EnumMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerEventMakeCommand()
    {
        $this->app->singleton(EventMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\EventMakeCommand => new EventMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerExceptionMakeCommand()
    {
        $this->app->singleton(ExceptionMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ExceptionMakeCommand => new ExceptionMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerFactoryMakeCommand()
    {
        $this->app->singleton(FactoryMakeCommand::class, fn ($app): \Illuminate\Database\Console\Factories\FactoryMakeCommand => new FactoryMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerEventClearCommand()
    {
        $this->app->singleton(EventClearCommand::class, fn ($app): \Illuminate\Foundation\Console\EventClearCommand => new EventClearCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerInterfaceMakeCommand()
    {
        $this->app->singleton(InterfaceMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\InterfaceMakeCommand => new InterfaceMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerJobMakeCommand()
    {
        $this->app->singleton(JobMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\JobMakeCommand => new JobMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerJobMiddlewareMakeCommand()
    {
        $this->app->singleton(JobMiddlewareMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\JobMiddlewareMakeCommand => new JobMiddlewareMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerListenerMakeCommand()
    {
        $this->app->singleton(ListenerMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ListenerMakeCommand => new ListenerMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMailMakeCommand()
    {
        $this->app->singleton(MailMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\MailMakeCommand => new MailMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMiddlewareMakeCommand()
    {
        $this->app->singleton(MiddlewareMakeCommand::class, fn ($app): \Illuminate\Routing\Console\MiddlewareMakeCommand => new MiddlewareMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerModelMakeCommand()
    {
        $this->app->singleton(ModelMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ModelMakeCommand => new ModelMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerNotificationMakeCommand()
    {
        $this->app->singleton(NotificationMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\NotificationMakeCommand => new NotificationMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerNotificationTableCommand()
    {
        $this->app->singleton(NotificationTableCommand::class, fn ($app): \Illuminate\Notifications\Console\NotificationTableCommand => new NotificationTableCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerObserverMakeCommand()
    {
        $this->app->singleton(ObserverMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ObserverMakeCommand => new ObserverMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerPolicyMakeCommand()
    {
        $this->app->singleton(PolicyMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\PolicyMakeCommand => new PolicyMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerProviderMakeCommand()
    {
        $this->app->singleton(ProviderMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ProviderMakeCommand => new ProviderMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueForgetCommand()
    {
        $this->app->singleton(ForgetFailedQueueCommand::class);
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueListenCommand()
    {
        $this->app->singleton(QueueListenCommand::class, fn ($app): \Illuminate\Queue\Console\ListenCommand => new QueueListenCommand($app['queue.listener']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueMonitorCommand()
    {
        $this->app->singleton(QueueMonitorCommand::class, fn ($app): \Illuminate\Queue\Console\MonitorCommand => new QueueMonitorCommand($app['queue'], $app['events']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueuePruneBatchesCommand()
    {
        $this->app->singleton(QueuePruneBatchesCommand::class, fn (): \Illuminate\Queue\Console\PruneBatchesCommand => new QueuePruneBatchesCommand());
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueuePruneFailedJobsCommand()
    {
        $this->app->singleton(QueuePruneFailedJobsCommand::class, fn (): \Illuminate\Queue\Console\PruneFailedJobsCommand => new QueuePruneFailedJobsCommand());
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueRestartCommand()
    {
        $this->app->singleton(QueueRestartCommand::class, fn ($app): \Illuminate\Queue\Console\RestartCommand => new QueueRestartCommand($app['cache.store']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueWorkCommand()
    {
        $this->app->singleton(QueueWorkCommand::class, fn ($app): \Illuminate\Queue\Console\WorkCommand => new QueueWorkCommand($app['queue.worker'], $app['cache.store']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueFailedTableCommand()
    {
        $this->app->singleton(FailedTableCommand::class, fn ($app): \Illuminate\Queue\Console\FailedTableCommand => new FailedTableCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueTableCommand()
    {
        $this->app->singleton(TableCommand::class, fn ($app): \Illuminate\Queue\Console\TableCommand => new TableCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerQueueBatchesTableCommand()
    {
        $this->app->singleton(BatchesTableCommand::class, fn ($app): \Illuminate\Queue\Console\BatchesTableCommand => new BatchesTableCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRequestMakeCommand()
    {
        $this->app->singleton(RequestMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\RequestMakeCommand => new RequestMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerResourceMakeCommand()
    {
        $this->app->singleton(ResourceMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ResourceMakeCommand => new ResourceMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRuleMakeCommand()
    {
        $this->app->singleton(RuleMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\RuleMakeCommand => new RuleMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerScopeMakeCommand()
    {
        $this->app->singleton(ScopeMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\ScopeMakeCommand => new ScopeMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerSeederMakeCommand()
    {
        $this->app->singleton(SeederMakeCommand::class, fn ($app): \Illuminate\Database\Console\Seeds\SeederMakeCommand => new SeederMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerSessionTableCommand()
    {
        $this->app->singleton(SessionTableCommand::class, fn ($app): \Illuminate\Session\Console\SessionTableCommand => new SessionTableCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRouteCacheCommand()
    {
        $this->app->singleton(RouteCacheCommand::class, fn ($app): \Illuminate\Foundation\Console\RouteCacheCommand => new RouteCacheCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRouteClearCommand()
    {
        $this->app->singleton(RouteClearCommand::class, fn ($app): \Illuminate\Foundation\Console\RouteClearCommand => new RouteClearCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRouteListCommand()
    {
        $this->app->singleton(RouteListCommand::class, fn ($app): \Illuminate\Foundation\Console\RouteListCommand => new RouteListCommand($app['router']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerSeedCommand()
    {
        $this->app->singleton(SeedCommand::class, fn ($app): \Illuminate\Database\Console\Seeds\SeedCommand => new SeedCommand($app['db']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerTestMakeCommand()
    {
        $this->app->singleton(TestMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\TestMakeCommand => new TestMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerTraitMakeCommand()
    {
        $this->app->singleton(TraitMakeCommand::class, fn ($app): \Illuminate\Foundation\Console\TraitMakeCommand => new TraitMakeCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerVendorPublishCommand()
    {
        $this->app->singleton(VendorPublishCommand::class, fn ($app): \Illuminate\Foundation\Console\VendorPublishCommand => new VendorPublishCommand($app['files']));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerViewClearCommand()
    {
        $this->app->singleton(ViewClearCommand::class, fn ($app): \Illuminate\Foundation\Console\ViewClearCommand => new ViewClearCommand($app['files']));
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return array_merge(array_values($this->commands), array_values($this->devCommands));
    }
}
