<?php

namespace App\Livewire;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;

class MidtransNotification extends Controller
{
    public $statusMessage;

    public function callback(Request $request)
    {
        Log::info('Midtrans Callback: ', $request->all());
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $hashed = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . config('services.midtrans.server_key'));
        if ($hashed === $request->signature_key) {
            if ($request->transaction_status === 'settlement') {
                $order = Order::find($request->order_id);
                $order->update(['payment_status' => 'paid']);
                $order->save();
            }
        }

        return view('livewire.midtrans-notification', ['statusMessage' => $this->statusMessage]);
    }
    public function render()
    {
        return view('livewire.midtrans-notification');
    }
}
