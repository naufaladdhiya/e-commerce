<?php

namespace App\Livewire;

use App\Models\Order;
use Illuminate\Http\Request;
use Livewire\Component;

class MidtransNotification extends Component
{
    public $statusMessage;

    public function handleNotification(Request $request)
    {
        $order_id = $request->order_id;
        $transaction_status = $request->transaction_status;

        $order = Order::find($order_id);

        if ($order) {
            if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
                $order->payment_status = 'paid';
                $order->status = 'delivered';
                $order->save();

                $this->statusMessage = 'Payment Success';
            } else {
                $this->statusMessage = 'Payment Failed';
            }
        } else {
            $this->statusMessage = 'Order not found';
        }

        return view('livewire.midtrans-notification', ['statusMessage' => $this->statusMessage]);
    }
    public function render()
    {
        return view('livewire.midtrans-notification');
    }
}
