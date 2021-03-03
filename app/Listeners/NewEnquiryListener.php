<?php

namespace App\Listeners;

use App\Events\NewEnquiry;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewEnquiryListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  NewEnquiry  $event
     * @return void
     */
    public function handle(NewEnquiry $event)
    {

    }
}
