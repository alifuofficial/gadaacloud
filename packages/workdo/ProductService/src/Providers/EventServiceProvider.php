<?php

namespace Workdo\ProductService\Providers;

use App\Events\PostPurchaseInvoice;
use App\Events\ApprovePurchaseReturn;
use App\Events\CompleteSalesReturn;
use App\Events\PostSalesInvoice;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Workdo\ProductService\Listeners\PostPurchaseInvoiceListener;
use Workdo\ProductService\Listeners\ApprovePurchaseReturnListener;
use Workdo\ProductService\Listeners\CompleteSalesReturnListener;
use Workdo\ProductService\Listeners\ConvertSalesRetainerListener;
use Workdo\ProductService\Listeners\PosCreateListener;
use Workdo\ProductService\Listeners\PostSalesInvoiceListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PostPurchaseInvoice::class => [
            PostPurchaseInvoiceListener::class,
        ],
        PostSalesInvoice::class => [
            PostSalesInvoiceListener::class,
        ],
        ApprovePurchaseReturn::class => [
            ApprovePurchaseReturnListener::class,
        ],
        CompleteSalesReturn::class => [
            CompleteSalesReturnListener::class,
        ],
    ];

    public function register(): void
    {
        if (class_exists(\Workdo\Pos\Events\CreatePos::class)) {
            $this->listen[\Workdo\Pos\Events\CreatePos::class] = [
                PosCreateListener::class,
            ];
        }

        if (class_exists(\Workdo\Retainer\Events\ConvertSalesRetainer::class)) {
            $this->listen[\Workdo\Retainer\Events\ConvertSalesRetainer::class] = [
                ConvertSalesRetainerListener::class,
            ];
        }

        parent::register();
    }
}
