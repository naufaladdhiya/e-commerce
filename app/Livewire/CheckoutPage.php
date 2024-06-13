<?php

namespace App\Livewire;

use App\Helpers\CartManagement;
use App\Models\Address;
use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Checkout')]

class CheckoutPage extends Component
{

    public $first_name;
    public $last_name;

    public $phone;

    public $street_address;

    public $city;

    public $state;

    public $zip_code;

    public $payment_method;

    public function mount()
    {
        $cart_items = CartManagement::getCartItemsFromCookie();
        if (count($cart_items) === 0) {
            return redirect('/products');
        }
    }

    public function placeOrder()
    {
        $this->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'required',
            'street_address' => 'required',
            'city' => 'required',
            'state' => 'required',
            'zip_code' => 'required',
            'payment_method' => 'required',
        ]);

        $cart_items = CartManagement::getCartItemsFromCookie();

        $line_items = [];

        foreach ($cart_items as $item) {
            $line_items[] = [
                'price_data' => [
                    'currency' => 'idr',
                    'unit_amount' => $item['unit_amount'] * 100,
                    'product_data' => [
                        'name' => $item['name'],
                    ],
                ],
                'quantity' => $item['quantity'],
            ];
        }

        $order = new Order();
        $order->user_id = auth()->user()->id;
        $order->grand_total = CartManagement::calculateTotalPrice($cart_items);
        $order->payment_method = $this->payment_method;
        $order->payment_status = 'pending';
        $order->status = 'new';
        $order->currency = 'idr';
        $order->shipping_amount = 0;
        $order->shipping_method = 'free';
        $order->notes = 'Order placed by ' . auth()->user()->name;

        $address = new Address();
        $address->first_name = $this->first_name;
        $address->last_name = $this->last_name;
        $address->phone = $this->phone;
        $address->street_address = $this->street_address;
        $address->city = $this->city;
        $address->state = $this->state;
        $address->zip_code = $this->zip_code;

        $redirect_url = '';

        if ($this->payment_method === 'stripe') {
            // $redirect_url = route('stripe.checkout');
        } else {
            $redirect_url = route('success');
        }

        $order->save();
        $address->order_id = $order->id;
        $address->save();
        $order->orderItems()->createMany($cart_items);
        CartManagement::clearCartFromCookie();
        return redirect($redirect_url);
    }
    public function render()
    {
        $cart_items = CartManagement::getCartItemsFromCookie();
        $grand_total = CartManagement::calculateTotalPrice($cart_items);
        return view('livewire.checkout-page', [
            'cart_items' => $cart_items,
            'grand_total' => $grand_total
        ]);
    }
}
