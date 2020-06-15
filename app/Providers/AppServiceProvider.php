<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Statamic\Statamic;
use Statamic\StaticSite\Generator;
use Illuminate\Support\Facades\Artisan;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Generator $ssg)
    {
        // Statamic::script('app', 'cp');
        // Statamic::style('app', 'cp');

        $ssg->after(function () {
            // eg. copy directory to some server
            $paginations = config('statamic.ssg.paginate');

            foreach ($paginations as $paginate) {
                // for each collection defined: call the artisan paginate command
                Artisan::call('paginate:partial', [
                    'collection' => $paginate['collection'],
                    '--template' => $paginate['template'],
                    '--paginate' => $paginate['paginate']
                ]);
            }
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
