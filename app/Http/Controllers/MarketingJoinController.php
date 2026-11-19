<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppMarketingList;
use App\Support\InstituteSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingJoinController extends Controller
{
    public function show(): View
    {
        return view('marketing.join', [
            'institute' => InstituteSettings::brandName(),
            'saved' => false,
            'notice' => null,
        ]);
    }

    public function store(Request $request, WhatsAppMarketingList $list): View
    {
        $name = trim((string) $request->input('name'));
        $result = $list->add($name, (string) $request->input('mobile'), 'landing');
        $notice = match ($result) {
            'added' => 'You are on the list. The institute can message you on WhatsApp.',
            'duplicate' => 'This number is already on the list.',
            default => 'Enter a 10-digit mobile number.',
        };

        return view('marketing.join', [
            'institute' => InstituteSettings::brandName(),
            'saved' => $result === 'added',
            'notice' => $notice,
        ]);
    }
}
