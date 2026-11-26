<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Admin\Services\DesignationService;
use PHPUnit\Framework\TestCase;

/** Which institutions have their quality assurance met through the Private Training (PTIRU) pathway. */
class DesignationRulesTest extends TestCase
{
    public function test_ptiru_pathway_matches_the_current_and_legacy_labels_only(): void
    {
        $this->assertTrue(DesignationService::ptiruRequired(DesignationService::PTIRU_QA_MET_THROUGH));
        $this->assertTrue(DesignationService::ptiruRequired(DesignationService::LEGACY_PTIB_QA_MET_THROUGH));
        $this->assertFalse(DesignationService::ptiruRequired('Public Institution Legislation'));
        $this->assertFalse(DesignationService::ptiruRequired('Languages Canada Accreditation'));
        $this->assertFalse(DesignationService::ptiruRequired(null));
    }
}
