<?php

namespace App\Jobs;

use App\Modules\Matches\Actions\CreateEditSetsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSet implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $data)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $createEditSetsAction = new CreateEditSetsAction();
        $createEditSetsAction($this->data);
    }
}
