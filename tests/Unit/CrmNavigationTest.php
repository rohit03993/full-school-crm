<?php

namespace Tests\Unit;

use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Tests\TestCase;

class CrmNavigationTest extends TestCase
{
    public function test_daily_use_groups_appear_before_configuration_groups(): void
    {
        $order = CrmNavigation::groupOrder();

        $leadsIndex = array_search(CrmMenuLabels::GROUP_LEADS, $order, true);
        $studentsIndex = array_search(CrmMenuLabels::GROUP_STUDENTS, $order, true);
        $academicsIndex = array_search(CrmMenuLabels::GROUP_ACADEMICS, $order, true);
        $whatsappIndex = array_search(CrmMenuLabels::GROUP_WHATSAPP, $order, true);
        $setupIndex = array_search(CrmMenuLabels::GROUP_SETTINGS, $order, true);
        $adminIndex = array_search(CrmMenuLabels::GROUP_ADMIN, $order, true);
        $websiteIndex = array_search(CrmMenuLabels::GROUP_WEBSITE, $order, true);

        $this->assertLessThan($whatsappIndex, $leadsIndex);
        $this->assertLessThan($whatsappIndex, $studentsIndex);
        $this->assertLessThan($whatsappIndex, $academicsIndex);
        $this->assertLessThan($setupIndex, $whatsappIndex);
        $this->assertLessThan($adminIndex, $setupIndex);
        $this->assertLessThan($websiteIndex, $adminIndex);
        $this->assertLessThan($studentsIndex, $leadsIndex);
        $this->assertLessThan($academicsIndex, $studentsIndex);
    }

    public function test_navigation_groups_match_group_order(): void
    {
        $labels = array_map(
            fn ($group): string => (string) $group->getLabel(),
            CrmNavigation::navigationGroups(),
        );

        $this->assertSame(CrmNavigation::groupOrder(), $labels);
    }
}
