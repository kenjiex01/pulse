<?php

namespace Tests\Unit;

use App\Models\TimekeepingPolicy;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimekeepingPolicyOvertimeComputationModeTest extends TestCase
{
    #[Test]
    public function auto_ot_from_excess_only_when_mode_is_auto_for_that_day_type(): void
    {
        $policy = new TimekeepingPolicy([
            'excess_hour_id' => TimekeepingPolicySupport::EXCESS_HOUR_CONSIDER_OT,
            'regular_ot_computation_mode' => TimekeepingPolicySupport::OT_COMPUTATION_REQUIRE_FORMS_OT_ONLY,
            'is_ot_form_required' => TimekeepingPolicySupport::OT_COMPUTATION_AUTO,
        ]);

        $this->assertFalse(TimekeepingPolicySupport::allowsAutomaticOvertimeFromExcess($policy, true));
        $this->assertTrue(TimekeepingPolicySupport::allowsAutomaticOvertimeFromExcess($policy, false));

        $policy->regular_ot_computation_mode = TimekeepingPolicySupport::OT_COMPUTATION_AUTO;
        $policy->is_ot_form_required = TimekeepingPolicySupport::OT_COMPUTATION_REQUIRE_FORMS_ALL;

        $this->assertTrue(TimekeepingPolicySupport::allowsAutomaticOvertimeFromExcess($policy, true));
        $this->assertFalse(TimekeepingPolicySupport::allowsAutomaticOvertimeFromExcess($policy, false));
    }
}
