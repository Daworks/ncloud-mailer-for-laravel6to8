<?php
	
	namespace Daworks\NcloudMailer;
	
	use Illuminate\Support\ServiceProvider;
	use Illuminate\Mail\MailManager;
	
	class NcloudMailerServiceProvider extends ServiceProvider
	{
		public function boot()
		{
			$this->publishes([
				__DIR__ . '/../config/ncloud-mailer.php' => config_path('ncloud-mailer.php'),
			]);
			
			$this->app->make(MailManager::class)->extend('ncloud', function ($app) {
				
				if (!isset($app['config']['ncloud-mailer'])) {
					$app['config']['ncloud-mailer'] = [
						'auth_key'       => env('NCLOUD_AUTH_KEY'),
						'service_secret' => env('NCLOUD_SERVICE_SECRET'),
					];
				}
				
				$config = $app['config']['ncloud-mailer'];
				
				return new NcloudMailerDriver(
					$config['auth_key'],
					$config['service_secret']
				);
			});
		}
		
		public function register()
		{
			$this->mergeConfigFrom(
				__DIR__ . '/../config/ncloud-mailer.php', 'ncloud-mailer'
			);
		}
	}
