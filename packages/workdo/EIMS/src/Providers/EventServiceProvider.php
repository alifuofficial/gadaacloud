<?php

namespace Workdo\EIMS\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\PostSalesInvoice;
use Workdo\EIMS\Listeners\PostSalesInvoiceListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PostSalesInvoice::class => [
            PostSalesInvoiceListener::class,
        ],
    ];
}
