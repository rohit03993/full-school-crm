<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofDownloadController extends Controller
{
    public function preview(Payment $payment): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless($payment->isProofPreviewable(), 404);
        abort_unless(Storage::disk(PaymentService::DISK)->exists($payment->proof_image_path), 404);

        return Storage::disk(PaymentService::DISK)->response(
            $payment->proof_image_path,
            basename($payment->proof_image_path),
        );
    }

    public function download(Payment $payment): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless(Storage::disk(PaymentService::DISK)->exists($payment->proof_image_path), 404);

        return Storage::disk(PaymentService::DISK)->download(
            $payment->proof_image_path,
            basename($payment->proof_image_path),
        );
    }
}
