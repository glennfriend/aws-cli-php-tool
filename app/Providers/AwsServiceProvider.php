<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\ThirdParty\Aws;

class AwsServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Aws::class, function ($app) {
            $aws = new Aws();
            $profile = env('AWS_PROFILE');
            $ownerId = env('AWS_OWNER_ID');

            $aws->setOwnerId($ownerId);
            $aws->setProfile($profile);
            return $aws;
        });

    }

    /**
     * @return void
     */
    public function boot()
    {
        //
    }
}
