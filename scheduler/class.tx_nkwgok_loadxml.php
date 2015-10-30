<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Nils K. Windisch (windisch@sub.uni-goettingen.de)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

define('NKWGOKMaxHierarchy', 31);

/**
 * Class "tx_nkwgok_loadxml" provides task procedures
 */
class tx_nkwgok_loadxml extends \TYPO3\CMS\Scheduler\Task\AbstractTask
{

    /**
     * Stores the hitcount for each notation.
     * Key: classifiaction system string => Value: Array with
     *        Key: notation => Value: hits for this notation
     * @var array
     */
    private $hitCounts;


    /**
     * Function executed from the Scheduler.
     * @return boolean TRUE if success, otherwise FALSE
     */
    public function execute()
    {
        set_time_limit(1200);

        // Remove records with statusID 1. These should not be around, but can
        // exist if a previous run of this task was cancelled.
        $GLOBALS['TYPO3_DB']->exec_DELETEquery(tx_nkwgok_utility::dataTable, 'statusID = 1');

        // Load hit counts.
        $this->hitCounts = $this->loadHitCounts();

        // Load XML files. Process those coming from csv files first as they can
        // be quite large and we are less likely to run into memory limits this way.
        $result = $this->loadXMLForType(tx_nkwgok_utility::recordTypeCSV);
        $result &= $this->loadXMLForType(tx_nkwgok_utility::recordTypeGOK);
        $result &= $this->loadXMLForType(tx_nkwgok_utility::recordTypeBRK);

        // Delete all old records with statusID 1, then switch all new records to statusID 0.
        $GLOBALS['TYPO3_DB']->exec_DELETEquery(tx_nkwgok_utility::dataTable, 'statusID = 0');
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(tx_nkwgok_utility::dataTable, 'statusID = 1', ['statusID' => 0]);

        \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('loadXML Scheduler Task: Import of subject hierarchy XML to TYPO3 database completed',
            tx_nkwgok_utility::extKey, 1);

        return $result;
    }

    /**
     * Load hitcounts from fileadmin/gok/hitcounts/*.xml.
     * These files are downloaded from the OPAC by the loadFromOpac Scheduler task.
     *
     * @return array with Key: classification system string => Value: Array with Key: notation => Value: hits for this notation
     */
    private function loadHitCounts()
    {
        $hitCountFolder = PATH_site . '/fileadmin/gok/hitcounts/';
        $fileList = $this->fileListAtPathForType($hitCountFolder, 'all');

        $hitCounts = [];
        if (is_array($fileList)) {
            foreach ($fileList as $xmlPath) {
                $xml = simplexml_load_file($xmlPath);
                if ($xml) {
                    $scanlines = $xml->xpath('/RESULT/SCANLIST/SCANLINE');
                    foreach ($scanlines as $scanline) {
                        $hits = Null;
                        $description = Null;
                        $hitCountType = Null;
                        foreach ($scanline->attributes() as $name => $value) {
                            if ($name === 'hits') {
                                $hits = (int)$value;
                            } else {
                                if ($name === 'description') {
                                    $description = (string)$value;
                                } else {
                                    if ($name === 'mnemonic') {
                                        $hitCountType = tx_nkwgok_utility::indexNameToType(strtolower((string)$value));
                                    }
                                }
                            }
                        }
                        if ($hits !== Null && $description !== Null && $hitCountType !== Null) {
                            if (!array_key_exists($hitCountType, $hitCounts)) {
                                $hitCounts[$hitCountType] = [];
                            }
                            $hitCounts[$hitCountType][$description] = $hits;
                        }
                    }
                } else {
                    \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('loadXML Scheduler Task: could not load/parse XML from ' . $xmlPath,
                        tx_nkwgok_utility::extKey, 3);
                }
            }
        } // end foreach

        foreach ($hitCounts as $hitCountType => $array) {
            \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('loadXML Scheduler Task: Loaded ' . count($array) . ' ' . $hitCountType . ' hit count entries.',
                tx_nkwgok_utility::extKey, 1);
        }

        return $hitCounts;
    }

    /**
     * Returns Array of file paths in $basePath of the given type.
     * The types are:
     *    * 'all': returns all *.xml files
     *  * 'gok': returns all gok-*.xml files
     *  * 'brk': returns all brk-*.xml files
     *  * otherwise the list given by 'all' - 'gok' - 'brk' is returned
     *
     * @param String $basePath
     * @param String $type
     * @return array of file paths in $basePath
     */
    private function fileListAtPathForType($basePath, $type)
    {
        if ($type === 'all') {
            $fileList = glob($basePath . '*.xml');
        } else {
            if ($type === tx_nkwgok_utility::recordTypeGOK || $type === tx_nkwgok_utility::recordTypeBRK) {
                $fileList = glob($basePath . $type . '-*.xml');
            } else {
                $fileList = glob($basePath . '*.xml');
                $gokFiles = glob($basePath . tx_nkwgok_utility::recordTypeGOK . '-*.xml');
                if (is_array($gokFiles)) {
                    $fileList = array_diff($fileList, $gokFiles);
                }
                $brkFiles = glob($basePath . tx_nkwgok_utility::recordTypeBRK . '-*.xml');
                if (is_array($brkFiles)) {
                    $fileList = array_diff($fileList, $brkFiles);
                }
            }
        }

        return $fileList;
    }

    /**
     * Loads the Pica XML records of the given type, tries to determine the hit
     * counts for each of them and inserts them to the database.
     *
     * @param String $type
     * @return Boolean
     */
    protected function loadXMLForType($type)
    {
        $XMLFolder = PATH_site . 'fileadmin/gok/xml/';
        $fileList = $this->fileListAtPathForType($XMLFolder, $type);

        if (is_array($fileList) && count($fileList) > 0) {
            // Parse XML files to extract just the tree structure.
            $subjectTree = $this->loadSubjectTree($fileList);

            // Compute total hit count sums.
            $totalHitCounts = $this->computeTotalHitCounts(tx_nkwgok_utility::rootNode, $subjectTree, $this->hitCounts);

            // Run through the files again, read all data, add the information
            // about parent elements and store it to our table in the database.
            foreach ($fileList as $xmlPath) {
                $rows = [];

                $xml = simplexml_load_file($xmlPath);
                foreach ($xml->xpath('/RESULT/SET/SHORTTITLE/record') as $recordElement) {
                    $recordType = $this->typeOfRecord($recordElement);

                    // Build complete record and insert into database.
                    // Discard records without a PPN.
                    $PPNs = $recordElement->xpath('datafield[@tag="003@"]/subfield[@code="0"]');
                    $PPN = trim($PPNs[0]);

                    $notations = $recordElement->xpath('datafield[@tag="045A"]/subfield[@code="a"]');
                    $notation = '';
                    if (count($notations) > 0) {
                        $notation = trim($notations[0]);
                    }

                    $mscs = $recordElement->xpath('datafield[@tag="044H" and subfield[@code="2"]="msc"]/subfield[@code="a"]');
                    $csvSearches = $recordElement->xpath('datafield[@tag="str"]/subfield[@code="a"]');

                    if ($PPN !== '' && array_key_exists($PPN, $subjectTree)) {
                        $search = '';
                        if ($recordType === tx_nkwgok_utility::recordTypeCSV) {
                            // Subject coming from CSV file with a CCL search query in the 'str/a' field.
                            if (count($csvSearches) > 0) {
                                $csvSearch = trim($csvSearches[0]);
                                $search = $csvSearch;
                            }
                        } else {
                            // Subject coming from a Pica authority record.
                            if (count($mscs) > 0) {
                                // Maths type GOK with an MSC type search term.
                                $msc = trim($mscs[0]);
                                $search = 'msc="' . $msc . '"';
                            } else {
                                if (count($notations) > 0) {
                                    if ($recordType === tx_nkwgok_utility::recordTypeGOK
                                        || $recordType === tx_nkwgok_utility::recordTypeBRK
                                    ) {
                                        // GOK or BRK OPAC search, using the corresponding index.
                                        $indexName = tx_nkwgok_utility::typeToIndexName($recordType);
                                        // Requires quotation marks around the search term as notations can begin
                                        // with three character strings that could be mistaken for index names.
                                        $search = $indexName . '="' . $notation . '"';
                                    } else {
                                        \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('loadXML Scheduler Task: Unknown record type »' . $recordType . '« in record PPN ' . $PPN . '. Skipping.',
                                            tx_nkwgok_utility::extKey, 3, $recordElement);
                                        continue;
                                    }
                                }
                            }
                        }

                        $treeElement = $subjectTree[$PPN];
                        $parentID = $treeElement['parent'];

                        // Use stored subject tree to determine hierarchy level.
                        // The hierarchy should be no deeper than 12 levels
                        // (for GOK) and 25 levels (for BRK).
                        // Cut off at 32 to prevent an infinite loop.
                        $hierarchy = 0;
                        $nextParent = $parentID;
                        while ($nextParent !== Null && $nextParent !== tx_nkwgok_utility::rootNode) {
                            $hierarchy++;
                            if (array_key_exists($nextParent, $subjectTree)) {
                                $nextParent = $subjectTree[$nextParent]['parent'];
                            } else {
                                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('loadXML Scheduler Task: Could not determine hierarchy level: Unknown parent PPN ' . $nextParent . ' for record PPN ' . $PPN . '. This needs to be fixed if he subject is meant to appear in a subject hierarchy.',
                                    tx_nkwgok_utility::extKey, 3, $recordElement);
                                $hierarchy = -1;
                                break;
                            }
                            if ($hierarchy > NKWGOKMaxHierarchy) {
                                \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('loadXML Scheduler Task: Hierarchy level for PPN ' . $PPN . ' exceeds the maximum limit of ' . NKWGOKMaxHierarchy . ' levels. This needs to be fixed, the subject tree may contain an infinite loop.',
                                    tx_nkwgok_utility::extKey, 3, $recordElement);
                                $hierarchy = -1;
                                break;
                            }
                        }

                        // Main subject name from field 045A $j.
                        $descr = '';
                        $descrs = $recordElement->xpath('datafield[@tag="045A"]/subfield[@code="j"]');
                        if (count($descrs) > 0) {
                            $descr = trim($descrs[0]);
                        }

                        // English version of the subject’s name from field 044F $a if $S is »d«.
                        $descr_en = '';
                        $descr_ens = $recordElement->xpath('datafield[@tag="044F" and subfield[@code="S"]="d"]/subfield[@code="a"]');
                        if (count($descr_ens) > 0) {
                            $descr_en = trim($descr_ens[0]);
                        }

                        // Alternate/additional description of the subject from field 044F $a if $S is »g« and $L is not »eng«
                        $descr_alternate = '';
                        $descr_alternates = $recordElement->xpath('datafield[@tag="044F" and subfield[@code="S"]="g" and not(subfield[@code="L"]="eng")]/subfield[@code="a"]');
                        if (count($descr_alternates) > 0) {
                            $descr_alternate = trim(implode('; ', $descr_alternates));
                        }

                        // English version of alternate/additional description of the subject from field 044F $a if $S is »g« and $L is  »eng«
                        $descr_alternate_en = '';
                        $descr_alternate_ens = $recordElement->xpath('datafield[@tag="044F" and subfield[@code="S"]="g" and subfield[@code="L"]="eng"]/subfield[@code="a"]');
                        if (count($descr_alternate_ens) > 0) {
                            $descr_alternate_en = trim(implode('; ', $descr_alternate_ens));
                        }

                        // Tags from the field tags artificially inserted by our CSV converter.
                        $tags = '';
                        $tagss = $recordElement->xpath('datafield[@tag="tags"]/subfield[@code="a"]');
                        if (count($tagss) > 0) {
                            $tags = trim($tagss[0]);
                        }

                        // Hitcount keys are lowercase.
                        // Set result count information:
                        // * for GOK, BRK, and MSC-type records: try to use hitcount
                        // * for CSV-type records: if only one LKL query, try to use hitcount, else use -1
                        // * otherwise: use 0
                        $hitCount = -1;
                        if (count($mscs) > 0) {
                            $msc = trim($mscs[0]);
                            if (array_key_exists(tx_nkwgok_utility::recordTypeMSC, $this->hitCounts)
                                && array_key_exists($msc, $this->hitCounts[tx_nkwgok_utility::recordTypeMSC])
                            ) {
                                $hitCount = $this->hitCounts[tx_nkwgok_utility::recordTypeMSC][$msc];
                            }
                        } else {
                            if (($recordType === tx_nkwgok_utility::recordTypeGOK
                                    || $recordType === tx_nkwgok_utility::recordTypeBRK)
                                && array_key_exists(strtolower($notation), $this->hitCounts[$recordType])
                            ) {
                                $hitCount = $this->hitCounts[$recordType][strtolower($notation)];
                            } else {
                                if ($recordType === tx_nkwgok_utility::recordTypeCSV && count($csvSearches) > 0) {
                                    // Try to detect simple GOK and MSC queries from CSV files so hit counts can be displayed for them.
                                    $csvSearch = trim($csvSearches[0]);

                                    $foundGOKs = [];
                                    $GOKPattern = '/^lkl=([a-zA-Z]*\s?[.X0-9]*)$/';
                                    preg_match($GOKPattern, $csvSearch, $foundGOKs);
                                    $foundGOK = strtolower($foundGOKs[1]);

                                    $foundMSCs = [];
                                    $MSCPattern = '/^msc=([0-9Xx][0-9Xx][A-Z-]*[0-9Xx]*)/';
                                    preg_match($MSCPattern, $csvSearch, $foundMSCs);
                                    $foundMSC = strtolower($foundMSCs[1]);

                                    if (count($foundGOKs) > 1
                                        && $foundGOK
                                        && array_key_exists(tx_nkwgok_utility::recordTypeGOK, $this->hitCounts)
                                        && array_key_exists($foundGOK,
                                            $this->hitCounts[tx_nkwgok_utility::recordTypeGOK])
                                    ) {
                                        $hitCount = $this->hitCounts[tx_nkwgok_utility::recordTypeGOK][$foundGOK];
                                    } else {
                                        if (count($foundMSCs) > 1
                                            && $foundMSC
                                            && array_key_exists(tx_nkwgok_utility::recordTypeMSC, $this->hitCounts)
                                            && array_key_exists($foundMSC,
                                                $this->hitCounts[tx_nkwgok_utility::recordTypeMSC])
                                        ) {
                                            $hitCount = $this->hitCounts[tx_nkwgok_utility::recordTypeMSC][$foundMSC];
                                            $recordType = tx_nkwgok_utility::recordTypeMSC;
                                        }
                                    }
                                } else {
                                    $hitCount = 0;
                                }
                            }
                        }

                        // Add total hit count information if it exists.
                        $totalHitCount = -1;
                        if (array_key_exists($PPN, $totalHitCounts)) {
                            $totalHitCount = $totalHitCounts[$PPN];
                        }

                        $childCount = count($treeElement['children']);

                        $rows[] = [
                            $PPN,
                            $hierarchy,
                            $notation,
                            $parentID,
                            $descr,
                            $descr_en,
                            $descr_alternate,
                            $descr_alternate_en,
                            $search,
                            $tags,
                            $childCount,
                            $recordType,
                            $hitCount,
                            $totalHitCount,
                            time(),
                            time(),
                            1
                        ];
                    }
                } // end of loop over subjects
                $keyNames = [
                    'ppn',
                    'hierarchy',
                    'notation',
                    'parent',
                    'descr',
                    'descr_en',
                    'descr_alternate',
                    'descr_alternate_en',
                    'search',
                    'tags',
                    'childcount',
                    'type',
                    'hitcount',
                    'totalhitcount',
                    'crdate',
                    'tstamp',
                    'statusID'
                ];
                $result = $GLOBALS['TYPO3_DB']->exec_INSERTmultipleRows(tx_nkwgok_utility::dataTable, $keyNames, $rows);

            } // end of loop over files

            $result = True;
        } else {
            \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('loadXML Scheduler Task: Found no XML files for type ' . $type . '.',
                tx_nkwgok_utility::extKey, 3);
            $result = True;
        }

        return $result;
    }

    /**
     * Goes through data files and creates information of the subject tree’s
     * structure from that.
     *
     * Storing the full data from all records would run into memory problems.
     * The resulting array just keeps the information we strictly need for
     * analysis.
     *
     * Returns an array. Keys are record IDs (PPNs), values are Arrays with:
     * * children => Array of strings (record IDs of child elements)
     * * [parent => string (record ID of parent element)]
     * * notation [gok|brk|msc|bkl|…] => string
     *
     * @param array $fileList list of XML files to read
     * @return array containing the subject tree structure
     */
    private function loadSubjectTree($fileList)
    {
        $tree = [];
        $tree[tx_nkwgok_utility::rootNode] = ['children' => []];

        // Run through all files once to gather information about the
        // structure of the data we process.
        foreach ($fileList as $xmlPath) {
            $xml = simplexml_load_file($xmlPath);
            $records = $xml->xpath('/RESULT/SET/SHORTTITLE/record');

            foreach ($records as $record) {
                $PPNs = $record->xpath('datafield[@tag="003@"]/subfield[@code="0"]');
                $PPN = (string)($PPNs[0]);

                // Create entry in the tree array if necessary.
                if (!array_key_exists($PPN, $tree)) {
                    $tree[$PPN] = ['children' => []];
                }

                $recordType = $this->typeOfRecord($record);
                $tree[$PPN]['type'] = $recordType;

                $myParentPPNs = $record->xpath('datafield[@tag="045C" and subfield[@code="4"] = "nueb"]/subfield[@code="9"]');
                if ($myParentPPNs && count($myParentPPNs) > 0) {
                    // Child record: store its PPN in the list of its parent’s children…
                    $parentPPN = (string)($myParentPPNs[0]);
                    if (!array_key_exists($parentPPN, $tree)) {
                        $tree[$parentPPN] = ['children' => []];
                    }
                    $tree[$parentPPN]['children'][] = $PPN;

                    // … and store the PPN of the parent record.
                    $tree[$PPN]['parent'] = $parentPPN;
                } else {
                    // has no parent record
                    $parentPPN = tx_nkwgok_utility::rootNode;
                    $tree[$parentPPN]['children'][] = $PPN;
                    $tree[$PPN]['parent'] = $parentPPN;
                }

                if ($recordType === tx_nkwgok_utility::recordTypeGOK || $recordType === tx_nkwgok_utility::recordTypeBRK) {
                    // Store notation information.
                    $notationStrings = $record->xpath('datafield[@tag="045A"]/subfield[@code="a"]');
                    if (count($notationStrings) > 0) {
                        $notationString = (string)($notationStrings[0]);
                        $notation = strtolower(trim($notationString));
                        $tree[$PPN][$recordType] = $notation;
                    }
                } else {
                    $queries = $record->xpath('datafield[@tag="str"]/subfield[@code="a"]');
                    if (count($queries) === 1) {
                        $query = (string)($queries[0]);
                        $foundQueries = NULL;
                        if (preg_match('/^msc=([^ ]*)$/', $query, $foundQueries) && count($foundQueries) === 2) {
                            $msc = $foundQueries[1];
                            $tree[$PPN][tx_nkwgok_utility::recordTypeMSC] = $msc;
                            $tree[$PPN]['type'] = tx_nkwgok_utility::recordTypeMSC;
                        }
                    }
                }

                // Store the last additional notation information (044H) of
                // each type (given in $2). In particular used for MSC.
                $extraNotations = $record->xpath('datafield[@tag="044H"]');
                foreach ($extraNotations as $extraNotation) {
                    $extraNotationTexts = $extraNotation->xpath('subfield[@code="a"]');
                    $extraNotationLabels = $extraNotation->xpath('subfield[@code="2"]');
                    if ($extraNotationTexts && $extraNotationLabels) {
                        $tree[$PPN][strtolower(trim($extraNotationLabels[0]))] = strtolower(trim($extraNotationTexts[0]));
                    }
                }

            } // end foreach $records
        } // end foreach $fileList

        return $tree;
    }

    /**
     * Returns the type of the $record passed.
     * Logs unknown record types.
     *
     * @param \DOMElement $record
     * @return string - gok|brk|csv|unknown
     */
    private function typeOfRecord($record)
    {
        $recordType = tx_nkwgok_utility::recordTypeUnknown;
        $recordTypes = $record->xpath('datafield[@tag="002@"]/subfield[@code="0"]');

        if ($recordTypes && count($recordTypes) === 1) {
            $recordTypeCode = (string)$recordTypes[0];

            if ($recordTypeCode === 'Tev') {
                $recordType = tx_nkwgok_utility::recordTypeGOK;
            } else {
                if ($recordTypeCode === 'Tov') {
                    $recordType = tx_nkwgok_utility::recordTypeBRK;
                } else {
                    if ($recordTypeCode === 'csv') {
                        $queryElements = $record->xpath('datafield[@tag="str"]/subfield[@code="a"]');
                        if ($queryElements && count($queryElements) === 1
                            && preg_match('/^msc=[0-9A-Zx-]*/', (string)$queryElements[0] > 0)
                        ) {
                            // Special case: an MSC record.
                            $recordType = tx_nkwgok_utility::recordTypeMSC;
                        } else {
                            // Regular case: a standard CSV record.
                            $recordType = tx_nkwgok_utility::recordTypeCSV;
                        }
                    }
                }
            }
        }

        if ($recordType === tx_nkwgok_utility::recordTypeUnknown) {
            \TYPO3\CMS\Core\Utility\GeneralUtility::devLog('loadXML Scheduler Task: Record of unknown type.',
                tx_nkwgok_utility::extKey, 1, [$record->saveXML()]);
        }

        return $recordType;
    }

    /**
     * Recursively go through $subjectTree and add up the $hitCounts to return a
     * total hit count including the hits for all child elements.
     *
     * @param String $startPPN - PPN to start at
     * @param array $subjectTree
     * @param array $hitCounts
     * @return array with Key: PPN => Value: sum of hit counts
     */
    private function computeTotalHitCounts($startPPN, $subjectTree, $hitCounts)
    {
        $totalHitCounts = [];
        $myHitCount = 0;
        if (array_key_exists($startPPN, $subjectTree)) {
            $record = $subjectTree[$startPPN];
            $type = $record['type'];
            $notation = strtolower($record[$type]);
            if (array_key_exists(tx_nkwgok_utility::recordTypeMSC, $record)
                && $type !== tx_nkwgok_utility::recordTypeBRK
            ) {
                $type = tx_nkwgok_utility::recordTypeMSC;
                $notation = strtolower($record[tx_nkwgok_utility::recordTypeMSC]);
            }

            if (count($record['children']) > 0) {
                // A parent node: recursively collect and add up the hit counts.
                foreach ($record['children'] as $childPPN) {
                    $childHitCounts = $this->computeTotalHitCounts($childPPN, $subjectTree, $hitCounts);
                    if (array_key_exists($childPPN, $childHitCounts)) {
                        $myHitCount += $childHitCounts[$childPPN];
                    }
                    $totalHitCounts += $childHitCounts;
                }

                if (array_key_exists($type, $hitCounts)
                    && array_key_exists($notation, $hitCounts[$type])
                ) {
                    $myHitCount += $hitCounts[$type][$notation];
                }
            } else {
                // A leaf node: just store its hit count.
                if (array_key_exists($type, $hitCounts)
                    && array_key_exists($notation, $hitCounts[$type])
                ) {
                    $myHitCount += $hitCounts[$type][$notation];
                }
            }
        }

        $totalHitCounts[$startPPN] = $myHitCount;

        return $totalHitCounts;
    }

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/nkwgok/lib/class.tx_nkwgok_loadxml.php']) {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/nkwgok/lib/class.tx_nkwgok_loadxml.php']);
}
