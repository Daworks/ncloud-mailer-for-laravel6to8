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

				$config = [
                    'auth_key' => config('ncloud-mailer.auth_key') ?? env('NCLOUD_AUTH_KEY'),
                    'service_secret' => config('ncloud-mailer.service_secret') ?? env('NCLOUD_SERVICE_SECRET')
                ];

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
