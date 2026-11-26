<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Traits\ControllerHelpers;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    use ControllerHelpers;

    public function index()
    {
        if(Auth::user()->can('manage-orders')){
            $orders = Order::with(['plan', 'user', 'total_coupon_used.coupon_detail'])
                ->where(function($q) {
                    if(Auth::user()->type == 'superadmin') {
                    } else {
                        $q->where('created_by', Auth::id());
                    }
                })
                ->when(request('search'), function($q) {
                    $search = request('search');
                    $q->where(function ($query) use ($search) {
                        $query->where('order_id', 'like', "%{$search}%")
                              ->orWhere('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%")
                              ->orWhere('plan_name', 'like', "%{$search}%");
                    });
                })
                ->when(request('order_id'), fn($q) => $q->where('order_id', 'like', '%' . request('order_id') . '%'))
                ->tap(fn($q) => $this->applySafeSort($q, ['order_id', 'name', 'email', 'plan_name', 'price', 'payment_status', 'created_at'], null, request('direction', 'desc'), 'id', 'desc'))
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            return Inertia::render('orders/index', [
                'orders' => $orders,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}