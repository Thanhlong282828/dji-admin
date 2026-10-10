<?php

namespace Tests\Unit\Architecture;

use Modules\CRM\Application\Commands\ConvertLeadToOpportunityCommand;
use Modules\CRM\Application\Commands\CreateLeadCommand;
use Modules\CRM\Application\Commands\CreateOpportunityCommand;
use Modules\CRM\Application\Commands\MoveOpportunityStageCommand;
use Modules\CRM\Application\Commands\UpdateLeadStatusCommand;
use Modules\CRM\Application\Queries\GetCrmPipelineQuery;
use Modules\CRM\Application\Queries\GetCrmProcessHelpQuery;
use Modules\CRM\Application\Queries\GetLeadWorkspaceQuery;
use Modules\CRM\Application\Queries\GetOpportunityWorkspaceQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CrmBoundedContextTest extends TestCase
{
    #[Test]
    public function crm_write_and_read_contracts_live_in_the_crm_bounded_context(): void
    {
        foreach ([
            CreateLeadCommand::class,
            UpdateLeadStatusCommand::class,
            ConvertLeadToOpportunityCommand::class,
            CreateOpportunityCommand::class,
            MoveOpportunityStageCommand::class,
            GetCrmPipelineQuery::class,
            GetLeadWorkspaceQuery::class,
            GetOpportunityWorkspaceQuery::class,
            GetCrmProcessHelpQuery::class,
        ] as $contract) {
            self::assertTrue(class_exists($contract), $contract.' must be autoloadable.');
            self::assertStringStartsWith('Modules\\CRM\\', $contract);
        }
    }
}
