<?php

namespace Tests\Unit;

use App\Models\ResearchGroup;
use App\Models\User;
use App\Services\AdviseeService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class AdviseeServiceTest extends TestCase
{
    public function test_it_resolves_a_linked_adviser_by_account_id(): void
    {
        $service = new AdviseeService();
        $group = new ResearchGroup(['technical_adviser_user_id' => 12, 'technical_adviser_name' => 'Same Name']);

        $this->assertSame(12, $service->resolvedAdviserUserId($group, new Collection([
            $this->panelist(12, 'Same', 'Name'),
            $this->panelist(24, 'Same', 'Name'),
        ])));
    }

    public function test_it_resolves_a_legacy_name_only_when_the_match_is_unique(): void
    {
        $service = new AdviseeService();
        $group = new ResearchGroup(['technical_adviser_name' => 'Maria Santos']);

        $this->assertSame(12, $service->resolvedAdviserUserId($group, new Collection([
            $this->panelist(12, 'Maria', 'Santos'),
            $this->panelist(24, 'Other', 'Panelist'),
        ])));
    }

    public function test_it_does_not_attribute_ambiguous_or_unmatched_legacy_names(): void
    {
        $service = new AdviseeService();
        $ambiguousGroup = new ResearchGroup(['technical_adviser_name' => 'Maria Santos']);
        $unmatchedGroup = new ResearchGroup(['technical_adviser_name' => 'Unknown Adviser']);
        $panelists = new Collection([
            $this->panelist(12, 'Maria', 'Santos'),
            $this->panelist(24, 'Maria', 'Santos'),
        ]);

        $this->assertNull($service->resolvedAdviserUserId($ambiguousGroup, $panelists));
        $this->assertNull($service->resolvedAdviserUserId($unmatchedGroup, $panelists));
    }

    public function test_it_does_not_resolve_a_linked_adviser_outside_the_category_candidates(): void
    {
        $service = new AdviseeService();
        $group = new ResearchGroup(['technical_adviser_user_id' => 12, 'technical_adviser_name' => 'Maria Santos']);

        $this->assertNull($service->resolvedAdviserUserId($group, new Collection([
            $this->panelist(24, 'Other', 'Panelist'),
        ])));
    }

    private function panelist(int $id, string $firstName, string $lastName): User
    {
        $panelist = new User();
        $panelist->setAttribute('id', $id);
        $panelist->setRelation('profile', (object) [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        return $panelist;
    }
}
