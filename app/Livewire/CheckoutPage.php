<?php

namespace App\Livewire;

use App\Helpers\CartManagement;
use App\Mail\OrderPlaced;
use App\Models\Address;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Midtrans\Config;
use Midtrans\Snap;



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
    public $snapToken;

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
                'id' => $item['product_id'],
                'price' => $item['unit_amount'],
                'quantity' => $item['quantity'],
                'name' => $item['name'],
            ];
        }

        $order = new Order();
        $order->user_id = auth()->user()->id;
        $order->grand_total = CartManagement::calculateTotalPrice($cart_items);
        $order->payment_method = $this->payment_method;
        $order->payment_status = 'pending';
        $order->status = 'new';
        $order->currency = 'IDR';
        $order->shipping_amount = 0;
        $order->shipping_method = 'free';
        $order->notes = 'Order placed by ' . auth()->user()->name;
        $order->save();

        $address = new Address();
        $address->first_name = $this->first_name;
        $address->last_name = $this->last_name;
        $address->phone = $this->phone;
        $address->street_address = $this->street_address;
        $address->city = $this->city;
        $address->state = $this->state;
        $address->zip_code = $this->zip_code;
        $address->order_id = $order->id;
        $address->save();

        $order->orderItems()->createMany($cart_items);
        Mail::to(request()->user())->send(new OrderPlaced($order));

        CartManagement::clearCartFromCookie();


        if ($this->payment_method === 'invoice') {
            Config::$serverKey = config('services.midtrans.server_key');
            Config::$isProduction = config('services.midtrans.is_production');
            Config::$isSanitized = true;
            Config::$is3ds = true;

            $transactionDetails = [
                'order_id' => $order->id,
                'gross_amount' => $order->grand_total,
            ];

            $customerDetails = [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => auth()->user()->email,
                'phone' => $this->phone,
            ];

            $itemDetails = $line_items;

            $snapToken = Snap::getSnapToken([
                'transaction_details' => $transactionDetails,
                'customer_details' => $customerDetails,
                'item_details' => $itemDetails,
            ]);

            $redirectUrl = "https://app.sandbox.midtrans.com/snap/v2/vtweb/" . $snapToken;
            return redirect($redirectUrl);
        } else {
            return redirect()->route('success');
        }
    }

    // public function callback(Request $request)
    // {
    //     Log::info('Midtrans Callback: ', $request->all());
    //     Config::$serverKey = config('services.midtrans.server_key');
    //     Config::$isProduction = config('services.midtrans.is_production');
    //     Config::$isSanitized = true;
    //     Config::$is3ds = true;

    //     $hashed = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . config('services.midtrans.server_key'));
    //     if ($hashed === $request->signature_key) {
    //         if ($request->transaction_status === 'settlement') {
    //             $order = Order::find($request->order_id);
    //             $order->update(['payment_status' => 'paid']);
    //             $order->save();
    //         }
    //     }
    // }

    public function render()
    {
        $cart_items = CartManagement::getCartItemsFromCookie();
        $grand_total = CartManagement::calculateTotalPrice($cart_items);

        return view('livewire.checkout-page', [
            'cart_items' => $cart_items,
            'grand_total' => $grand_total,
        ]);
    }
}
