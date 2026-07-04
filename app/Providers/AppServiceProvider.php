<?php

namespace App\Providers;

use App\Contracts\BackupServiceInterface;
use App\Services\BackupService;
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
            BackupServiceInterface::class,
            BackupService::class
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
                        $perPage = request()->perPage ?? null;
                        if (blank($perPage)) {
                            // set from session
                            $perPage = session()->get('perPage', 10);
                        }
                    }
                    $page = $page ?: Paginator::resolveCurrentPage($pageName);

                    // Convert the collection items to an array to match Eloquent's output
                    $results = $this->forPage($page, $perPage)->values();
                    $total = $this->toBase()->count();

                    return new class($results, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName]) extends LengthAwarePaginator {
                        public string $page_name;

                        public function __construct($items, $total, $perPage, $currentPage, array $options)
                        {
                            $this->page_name = $options['pageName'] ?? 'page';
                            parent::__construct($items, $total, $perPage, $currentPage, $options);
                        }

                        public function toArray(): array
                        {
                            return array_merge(parent::toArray(), ['page_name' => $this->page_name]);
                        }
                    };
                });
        }

        // Extend laravel default pagination
        Builder::macro('customPaginate', function ($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null) {
            $perPage = request()->input('perPage', null);
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

            return new class($results, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => $pageName]) extends LengthAwarePaginator {
                public string $page_name;

                public function __construct($items, $total, $perPage, $currentPage, array $options)
                {
                    $this->page_name = $options['pageName'] ?? 'page';
                    parent::__construct($items, $total, $perPage, $currentPage, $options);
                }

                public function toArray(): array
                {
                    return array_merge(parent::toArray(), ['page_name' => $this->page_name]);
                }
            };
        });
    }
}
