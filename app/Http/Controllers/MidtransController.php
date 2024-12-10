<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\Order;
use Midtrans\Config;

class MidtransController extends Controller
{
  public function callback(Request $request)
  {
    Log::info('Midtrans Callback: ', $request->all());
    Config::$serverKey = config('services.midtrans.server_key');
    Config::$isProduction = config('services.midtrans.is_production');

    $hashed = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . config('services.midtrans.server_key'));

    if ($hashed === $request->signature_key) {
      if ($request->transaction_status === 'settlement') {
        $order = Order::find($request->order_id);
        $order->update(['payment_status' => 'paid']);
        $order->save();
      }
    }
  }
}
