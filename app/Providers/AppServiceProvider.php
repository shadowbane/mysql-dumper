<?php

namespace App\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\BackupServiceInterface::class,
            \App\Services\BackupService::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @param  UrlGenerator  $url
     *
     * @throws BindingResolutionException
     *
     * @return void
     */
    public function boot(UrlGenerator $url): void
    {
        // prevent n+1 query
        Model::preventLazyLoading(! app()->isProduction());

        // add request identifier
        Request::macro('identifier', function () {
            return once(fn() => Str::ulid()->toBase32());
        });

        // Redirecting all requests to https
        if (config('system.default.redirect_https')) {
            $url->forceScheme('https');
            $this->app['request']->server->set('HTTPS', true);
        }

        // add paginate to collection
        if (! Collection::hasMacro('paginate')) {
            Collection::macro('paginate',
                function ($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
                    if (is_null($perPage)) {
                        $perPage = request()->get('perPage', null);
                        if (blank($perPage)) {
                            // set from session
                            $perPage = session()->get('perPage', 10);
                        }
                    }
                    $page = $page ?: Paginator::resolveCurrentPage($pageName);

                    // Convert the collection items to an array to match Eloquent's output
                    $results = $this->forPage($page, $perPage)->values();

                    return new LengthAwarePaginator(
                        $results,
                        $this->count(),
                        $perPage,
                        $page,
                        [
                            'path' => Paginator::resolveCurrentPath(),
                            'pageName' => $pageName,
                        ]
                    );
                });
        }

        // Extend laravel default pagination
        Builder::macro('customPaginate', function ($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null) {
            $perPage = request()->get('perPage', null);
            // Use session's per page first, then use the default per page
            if (blank($perPage)) {
                $perPage = session()->get('perPage', 10);
            }

            $page = $page ?: Paginator::resolveCurrentPage($pageName);

            $total = value($total) ?? $this->toBase()->getCountForPagination();

            $perPage = value($perPage, $total) ?: $this->model->getPerPage();

            $results = $total
                ? $this->forPage($page, $perPage)->get($columns)
                : $this->model->newCollection();

            return $this->paginator($results, $total, $perPage, $page, [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]);
        });
    }
}
