<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Admin\Services\DesignationService;
use PHPUnit\Framework\TestCase;

/** PTIRU / standing rules that decide whether an approved application can designate the institution. */
class DesignationRulesTest extends TestCase
{
    public function test_only_in_good_standing_is_good(): void
    {
        $this->assertTrue(DesignationService::standingIsGood('In Good Standing'));
        $this->assertFalse(DesignationService::standingIsGood('Not In Good Standing'));
        $this->assertFalse(DesignationService::standingIsGood(''));
        $this->assertFalse(DesignationService::standingIsGood(null));
    }

    public function test_ptiru_standing_applies_only_to_the_private_training_pathway(): void
    {
        $this->assertTrue(DesignationService::ptiruRequired(DesignationService::PTIRU_QA_MET_THROUGH));
        $this->assertTrue(DesignationService::ptiruRequired(DesignationService::LEGACY_PTIB_QA_MET_THROUGH));
        $this->assertFalse(DesignationService::ptiruRequired('Public Institution Legislation'));
        $this->assertFalse(DesignationService::ptiruRequired('Languages Canada Accreditation'));
        $this->assertFalse(DesignationService::ptiruRequired(null));
    }

    public function test_ptiru_pathway_needs_good_ptiru_standing_to_designate(): void
    {
        $this->assertTrue(DesignationService::ptiruPermitsDesignation(DesignationService::PTIRU_QA_MET_THROUGH, 'In Good Standing'));
        $this->assertFalse(DesignationService::ptiruPermitsDesignation(DesignationService::PTIRU_QA_MET_THROUGH, 'Not In Good Standing'));
        $this->assertFalse(DesignationService::ptiruPermitsDesignation(DesignationService::LEGACY_PTIB_QA_MET_THROUGH, null));
    }

    public function test_other_pathways_ignore_ptiru_standing(): void
    {
        $this->assertTrue(DesignationService::ptiruPermitsDesignation('Ministry Review Process', null));
        $this->assertTrue(DesignationService::ptiruPermitsDesignation('Public Institution Legislation', 'Not In Good Standing'));
    }
}
