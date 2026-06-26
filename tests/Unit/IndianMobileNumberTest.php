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

    #[DataProvider('spreadsheetMobileProvider')]
    public function test_normalize_from_spreadsheet_handles_excel_values(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, IndianMobileNumber::normalizeFromSpreadsheet($input));
    }

    /**
     * @return array<string, array{0: mixed, 1: ?string}>
     */
    public static function spreadsheetMobileProvider(): array
    {
        return [
            'ten digit string' => ['8410054825', '8410054825'],
            'twelve digit string' => ['919027620525', '9027620525'],
            'excel float with country code' => [919027620525.0, '9027620525'],
            'excel float ten digit' => [8410054825.0, '8410054825'],
            'excel scientific string' => ['9.19027620525E+11', '9027620525'],
        ];
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
            'ten digit plain' => ['8410054825', '8410054825'],
            'twelve digit with ninety one' => ['919027620525', '9027620525'],
            'excel scientific string' => ['9.18320936486E+11', '8320936486'],
            'invalid short' => ['981100', null],
            'invalid start digit' => ['5811000001', null],
        ];
    }
}
