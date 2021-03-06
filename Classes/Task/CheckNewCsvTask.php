<?php

declare(strict_types=1);

namespace Subugoe\Nkwgok\Task;

use Subugoe\Nkwgok\Command\CheckNewCsvCommand;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Scheduler Task - just a wrapper for the real task to appear in the scheduler menu.
 */
class CheckNewCsvTask extends Task
{
    public function execute()
    {
        $task = GeneralUtility::makeInstance(CheckNewCsvCommand::class, $this->getTaskTitle());

        return $task->execute($this->input, $this->output);
    }
}
