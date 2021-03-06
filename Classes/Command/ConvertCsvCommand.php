<?php

declare(strict_types=1);

namespace Subugoe\Nkwgok\Command;

use Subugoe\Nkwgok\Importer\ConvertCsv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TYPO3 Scheduler task to process CSV files with subject tree information.
 *
 * The file format is described in the processCSVFile function.
 */
class ConvertCsvCommand extends Command
{
    public function configure()
    {
        parent::configure();
        $this->setDescription('Task to process CSV files with subject tree information');
    }

    /**
     * Function executed from the Scheduler.
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $service = GeneralUtility::makeInstance(ConvertCsv::class);

        return $service->run() ? 0 : 1;
    }
}
