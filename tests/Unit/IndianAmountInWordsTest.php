<?php

namespace Tests\Unit;

use App\Support\IndianAmountInWords;
use PHPUnit\Framework\TestCase;

class IndianAmountInWordsTest extends TestCase
{
    public function test_formats_rupees_and_paise(): void
    {
        $this->assertSame('One Thousand Five Hundred Rupees Only', IndianAmountInWords::format(1500));
        $this->assertSame('Three Thousand Rupees and Fifty Paise Only', IndianAmountInWords::format(3000.50));
    }
}
