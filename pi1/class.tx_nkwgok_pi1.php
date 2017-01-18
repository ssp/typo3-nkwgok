<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2010 Nils K. Windisch <windisch@sub.uni-goettingen.de>
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

/**
 * See the ChangeLog or git repository for details.
 */
class tx_nkwgok_pi1 extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin
{

    /**
     * Main method of the PlugIn
     *
     * @param string $content : The PlugIn content
     * @param array $conf : The PlugIn configuration
     * @return string The content that is displayed on the website
     */
    public function main($content, $conf)
    {
        // basic
        $this->pi_setPiVarDefaults();
        $this->pi_loadLL();
        $this->pi_initPIflexform();
        $this->pi_USER_INT_obj = 1;

        // CSS
        $this->addStylesheet();

        // get getvars
        $arguments = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET('tx_nkwgok');

        // get flexform
        $arguments['notation'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'source', 'sDEF');
        $altSource = trim($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'altSource', 'sDEF'));
        // alternative source overrides first definition
        if ($altSource) {
            $arguments['notation'] = $altSource;
        }

        // unique expand array
        if (array_key_exists('expand', $arguments) && is_array($arguments['expand'])) {
            $arguments['expand'] = array_unique($arguments['expand']);
        }

        $arguments['style'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'style', 'sDEF');
        $arguments['showGOKID'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'showGOKID');
        $arguments['omitXXX'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'omitXXX');
        $arguments['objectID'] = $this->cObj->data['uid'];
        $arguments['pageLink'] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
        $arguments['language'] = $GLOBALS['TSFE']->lang;

        /** @var \tx_nkwgok $nkwgok */
        $nkwgok = \tx_nkwgok::instantiateSubclassFor($arguments);
        $doc = $nkwgok->getMarkup();
        $content .= $doc->saveHTML();

        return $content;
    }

    /**
     * Helper function to add our default stylesheet or the one at the path
     * set up in Extension Manager configuration to the page’s head.
     */
    protected function addStylesheet()
    {
        $nkwgokGlobalConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][tx_nkwgok_utility::extKey]);
        $cssPath = $nkwgokGlobalConf['CSSPath'];
        if (!$cssPath) {
            $cssPath = 'EXT:nkwgok/Resources/Public/Css/nkwgok.css';
        }

        $GLOBALS['TSFE']->pSetup['includeCSS.'][tx_nkwgok_utility::extKey] = $cssPath;
    }
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/nkwgok/pi1/class.tx_nkwgok_pi1.php']) {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/nkwgok/pi1/class.tx_nkwgok_pi1.php']);
}
