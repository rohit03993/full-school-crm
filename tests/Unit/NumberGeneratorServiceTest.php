<?php

namespace Tests\Unit;

use App\Enums\NumberSequenceType;
use App\Services\NumberGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_sequential_enquiry_numbers(): void
    {
        $service = app(NumberGeneratorService::class);

        $first = $service->generate(NumberSequenceType::Enquiry, 2026);
        $second = $service->generate(NumberSequenceType::Enquiry, 2026);

        $this->assertSame('CRM-ENQ-2026-000001', $first);
        $this->assertSame('CRM-ENQ-2026-000002', $second);
    }

    public function test_it_uses_correct_prefix_per_type(): void
    {
        $service = app(NumberGeneratorService::class);

        $this->assertSame('CRM-ADM-2026-000001', $service->generate(NumberSequenceType::Admission, 2026));
        $this->assertSame('CRM-2026-000001', $service->generate(NumberSequenceType::Enrollment, 2026));
        $this->assertSame('REC-2026-000001', $service->generate(NumberSequenceType::Receipt, 2026));
    }
}
