<?php

namespace App\Http\Controllers\Guest;
use Braintree\Gateway as Gateway;
use Braintree\Transaction as Transaction;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Mail\NewOrderReceived;
use App\Business;
use App\Order;

class OrderController extends Controller
{
  public function checkout(Business $business)
  {

    $gateway = new Gateway([
      'environment' => 'sandbox',
      'merchantId' => 'nyfdp2wz77gqqj29',
      'publicKey' => '8bdc65hxyy4pg56f',
      'privateKey' => 'e16fd716976555b304d8b2d18ad5ce55'
    ]);

    $token = $gateway->ClientToken()->generate();
    return view('guest.order-summary', compact('business', 'token', 'gateway'));
  }

  public function store(Request $request)
  {
    $data = $request->all();

    $order = new Order();
    $order->fill($data);
    $products= [];

    foreach ($data['products'] as $id => $product) {
      for ($i=0; $i < $data['quantities'][$id] ; $i++) {
        $products[] = $product;
      }
    }

    $gateway = new Gateway([
        'environment' => 'sandbox',
        'merchantId' => 'nyfdp2wz77gqqj29',
        'publicKey' => '8bdc65hxyy4pg56f',
        'privateKey' => 'e16fd716976555b304d8b2d18ad5ce55'
    ]);

    $result = $gateway->transaction()->sale([
        'amount' => $order->amount,
        'paymentMethodNonce' => 'fake-valid-nonce',
        'options' => [
        'submitForSettlement' => true
        ]
    ]);

    if ($result->success || !is_null($result->transaction)) {
      $transaction = $result->transaction;
      $order->success = 1;
      $order->save();
      $order->products()->attach($products);

      $mailableObject = new NewOrderReceived($order);
      Mail::to('prova@mail.it')->send($mailableObject);
      return view('guest.order-success', compact('transaction'));
    } else {
      $errors = [];
      foreach($result->errors->deepAll() as $error) {
        $errors[$error->code] = $error->message;
      }
      $order->success = 0;
      $order->save();
      $order->products()->attach($products);
      return view('guest.order-error', compact('errors'));
    }
  }
}