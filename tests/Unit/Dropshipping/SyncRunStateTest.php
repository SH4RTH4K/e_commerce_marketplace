<?php

namespace Tests\Unit\Dropshipping;

use App\Services\Dropshipping\Support\SyncRunState;
use PHPUnit\Framework\TestCase;

class SyncRunStateTest extends TestCase
{
    public function test_queued_runs_can_start_or_cancel_but_terminal_runs_cannot(): void
    {
        $this->assertTrue(SyncRunState::canStart(SyncRunState::QUEUED));
        $this->assertTrue(SyncRunState::canCancel(SyncRunState::QUEUED));
        $this->assertTrue(SyncRunState::canCancel(SyncRunState::RUNNING));
        $this->assertFalse(SyncRunState::canCancel(SyncRunState::COMPLETED));
        $this->assertTrue(SyncRunState::isTerminal(SyncRunState::CANCELLED));
    }

    public function test_items_only_start_for_running_runs_and_once(): void
    {
        $this->assertTrue(SyncRunState::canStartItem(SyncRunState::RUNNING, SyncRunState::ITEM_QUEUED));
        $this->assertFalse(SyncRunState::canStartItem(SyncRunState::CANCELLED, SyncRunState::ITEM_QUEUED));
        $this->assertFalse(SyncRunState::canStartItem(SyncRunState::RUNNING, SyncRunState::ITEM_RUNNING));
        $this->assertTrue(SyncRunState::isTerminalItem(SyncRunState::ITEM_SUCCEEDED));
        $this->assertTrue(SyncRunState::isTerminalItem(SyncRunState::ITEM_FAILED));
    }
}
