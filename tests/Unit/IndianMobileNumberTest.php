<?php

namespace Tests\Unit;

use App\Support\IndianMobileNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndianMobileNumberTest extends TestCase
{
    #[DataProvider('mobileProvider')]
    public function test_normalize_accepts_common_formats(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, IndianMobileNumber::normalize($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function mobileProvider(): array
    {
        return [
            'plain ten digits' => ['9811000001', '9811000001'],
            'with spaces' => ['9811 000 001', '9811000001'],
            'with plus ninety one' => ['+91 9811000001', '9811000001'],
            'with country code no plus' => ['919811000001', '9811000001'],
            'leading zero' => ['09811000001', '9811000001'],
            'invalid short' => ['981100', null],
            'invalid start digit' => ['5811000001', null],
        ];
    }
}
