<?php

namespace Modules\Ecommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ecommerce\Http\Resources\OrderResource;
use Modules\Ecommerce\Models\Order;
use Modules\Ecommerce\Models\Product;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with(['user', 'items.product']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%'.$request->search.'%')
                    ->orWhereHas('user', function ($userQuery) use ($request) {
                        $userQuery->where('name', 'like', '%'.$request->search.'%')
                            ->orWhere('email', 'like', '%'.$request->search.'%');
                    });
            });
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'orders' => OrderResource::collection($orders),
            ]);
        }

        return Inertia::render('Ecommerce::orders', [
            'orders' => [
                'data' => OrderResource::collection($orders->items())->resolve(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'filters' => $request->only(['search', 'status', 'payment_status']),
        ]);
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string'],
            'customer_notes' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'array'],
            'shipping_address' => ['nullable', 'array'],
        ]);

        try {
            DB::beginTransaction();

            $subtotal = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->track_inventory && $product->stock_quantity < $item['quantity']) {
                    return response()->json([
                        'message' => "Insufficient stock for product: {$product->name}",
                    ], 422);
                }

                $price = $product->effective_price;
                $itemSubtotal = $price * $item['quantity'];
                $subtotal += $itemSubtotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'subtotal' => $itemSubtotal,
                ];

                $product->decrementStock($item['quantity']);
                $product->increment('sales_count', $item['quantity']);
            }

            $tax = $validated['tax'] ?? 0;
            $shipping = $validated['shipping'] ?? 0;
            $discount = $validated['discount'] ?? 0;
            $total = $subtotal + $tax + $shipping - $discount;

            $order = Order::create([
                'user_id' => Auth::id(),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $validated['payment_method'] ?? null,
                'customer_notes' => $validated['customer_notes'] ?? null,
                'billing_address' => $validated['billing_address'] ?? null,
                'shipping_address' => $validated['shipping_address'] ?? null,
            ]);

            $order->items()->createMany($orderItems);

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load(['user', 'items.product']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, Order $order): Response|JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['user', 'items.product']);

        if ($request->expectsJson()) {
            return response()->json([
                'order' => OrderResource::make($order),
            ]);
        }

        return Inertia::render('Ecommerce::order', [
            'order' => OrderResource::make($order)->resolve(),
        ]);
    }

    /**
     * Update the specified order.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,processing,completed,cancelled,refunded'],
            'payment_status' => ['sometimes', 'in:unpaid,paid,refunded'],
            'payment_method' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'array'],
            'shipping_address' => ['nullable', 'array'],
        ]);

        $order->update($validated);

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order->fresh(['user', 'items.product']),
        ]);
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,completed,cancelled,refunded'],
        ]);

        if ($validated['status'] === 'cancelled' && ! $order->is_cancelled) {
            $order->cancel();
        } elseif ($validated['status'] === 'refunded' && ! $order->is_cancelled) {
            $order->refund();
        } else {
            $order->update(['status' => $validated['status']]);
        }

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order->fresh(['user', 'items.product']),
        ]);
    }

    /**
     * Mark order as paid.
     */
    public function markAsPaid(Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        if ($order->is_paid) {
            return response()->json([
                'message' => 'Order is already marked as paid',
            ], 422);
        }

        $order->markAsPaid();

        return response()->json([
            'message' => 'Order marked as paid successfully',
            'order' => $order->fresh(['user', 'items.product']),
        ]);
    }

    /**
     * Remove the specified order.
     */
    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);

        if ($order->is_completed || $order->is_paid) {
            return response()->json([
                'message' => 'Cannot delete completed or paid orders',
            ], 422);
        }

        foreach ($order->items as $item) {
            $item->product->incrementStock($item->quantity);
        }

        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully',
        ]);
    }
}
