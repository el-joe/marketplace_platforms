<?php

namespace App\Services\Customer;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\UploadedFile;

class BankTransferProofService
{
    public function upload(Order $order, UploadedFile $file, ?string $note = null): PaymentTransaction
    {
        $transaction = $order->transactions()
            ->where('gateway', $order->payment_gateway_code)
            ->latest('created_at')
            ->firstOrFail();

        $path = $file->store("orders/{$order->id}/payment-proofs", 'public');

        $transaction->update([
            'proof_file_path' => $path,
            'proof_uploaded_at' => now(),
            'note' => $note,
        ]);

        return $transaction->fresh();
    }
}
