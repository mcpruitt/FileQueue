<?php namespace Mcpruitt\FileQueue;

use Illuminate\Support\ServiceProvider;
use \Mcpruitt\Queue\Connectors\FileQueueConnector;

class FileQueueServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		\App::make('queue')->addConnector("file", function() {
      return new FileQueueConnector();
    });
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}