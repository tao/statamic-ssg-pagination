<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Markdown;
use Statamic\Facades\Yaml;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\View\View;

class StaticPagination extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paginate:partial {collection} {--template=} {--out-path=} {--paginate=20} {--filter=} {--is=} {--isnt=} {--sort="date:desc"}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and save pagination partials.';

    /**
     * Collection name
     */
    protected $collection;
    
    /**
     * Pagination template
     */
    protected $template;

    /**
     * Pagination size
     */
    protected $paginate;

    /**
     * Destination output path
     */
    protected $path;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function generatePartial($items, $total, $pages, $currentPage)
    {
        // render Statamic view
        $view = (new View)
            ->template($this->template)
            ->with([
                'entries' => $items, // N.B. make sure all partials use 'entries' as the tag pair to loop over
                'paginate' => [
                    'total_items' => $total,
                    'items_per_page' => $this->paginate,
                    'total_pages' => $pages,
                    'current_page' => $currentPage,
                ],
            ])
            ->render();

        // save rendered view
        $destination = "static/api/partials/{$this->path}/{$currentPage}/index.html";
        Storage::disk('local')->put($destination, $view);
        
        return true;
    }

    public function generatePagination()
    {
        // fetch the entries in the collection
        $collection = Entry::query()->where('collection', $this->collection);

        // NB optional filtering
        if ($this->option('is')) {
            // e.g. where type = archive
            $collection->where($this->option('filter'), $this->option('is'));
        }

        if ($this->option('isnt')) {
            // e.g. where type = archive
            $collection->where($this->option('filter'), '!=', $this->option('isnt'));
        }

        if ($this->option('sort')) {
            // e.g. sort by many date:desc|stars:desc
            // seperate each sort option by |
            $sorting = explode('|', $this->option('sort'));
            // for each sorting requested
            foreach ($sorting as $sortBy) {
                // seperate vars by :
                $orderBy = explode(':', $sortBy);
                list ($field, $order) = $orderBy;
                // order by field:order
                $collection->orderBy($field, $order);
            }
        }

        // fetch the total and get the entries
        $total = $collection->count();
        $entries = $collection->get();
        
        // determine the number of pages required
        $paginate = $this->paginate;
        $pages = ceil($total / $paginate);

        // track our progress
        // $bar = $this->output->createProgressBar($pages);
        // $bar->start();

        // process each page
        for ($page = 0; $page < $pages; $page++) {
            // order entries by date and offset by page
            $items = $collection->orderBy('date', 'desc')->offset($paginate*$page)->limit($paginate)->get();

            // generate the partial
            $currentPage = ($page + 1);
            $this->generatePartial($items, $total, $pages, $currentPage);

            // display the progress
            echo("[✔] paginate partial: /api/partials/{$this->path}/{$currentPage}" . PHP_EOL);
            // $bar->advance();
        }

        // $bar->finish();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->collection = $this->argument('collection');
        $this->template = $this->option('template');
        $this->paginate = $this->option('paginate');

        $this->path = $this->collection;
        if ($this->option('out-path')) { 
            // handle optional alternate out-put path,
            // i.e. /articles vs /articles/archive 
            // when --filter=type --is=archive (or) --isnt=archive
            $this->path = $this->option('out-path');
        }

        $this->generatePagination();
    }
}
