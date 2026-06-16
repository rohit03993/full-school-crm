<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentPortal\Concerns\ResolvesPortalStudent;
use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptDownloadController extends Controller
{
    use ResolvesPortalStudent;

    public function download(Payment $payment): StreamedResponse
    {
        $student = $this->portalStudent();

        abort_unless($payment->student_id === $student->id, 403);
        abort_unless($payment->hasReceiptPdf(), 404);
        abort_unless(Storage::disk(ReceiptService::DISK)->exists($payment->receipt_path), 404);

        return Storage::disk(ReceiptService::DISK)->download(
            $payment->receipt_path,
            "{$payment->receipt_number}.pdf",
        );
    }
}
