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
 * Changes 2011-2012 by Sven-S. Porst <porst@sub.uni-goettingen.de>
 * See the ChangeLog or git repository for details.
 */


define('NKWGOKExtKey', 'nkwgok');
define('NKWGOKQueryTable', 'tx_nkwgok_data');
define('NKWGOKQueryFields', 'ppn, gok, search, descr, descr_en, parent, childcount, hitcount, totalhitcount, fromopac');

/**
 * Class tx_nkwgok: provides output for the nkwgok extension.
 *
 * @package TYPO3
 * @author Nils K. Windisch
 * @author Sven-S. Porst
 * */
class tx_nkwgok extends tslib_pibase {

	/**
	 * @var Array
	 */
	protected $localisation;

	/**
	 * Provide our own localisation function as getLL() is not available when
	 * running in eID.
	 *
	 * @author Sven-S. Porst
	 * @param string $key key to look up in pi1/locallang.xml
	 * @param string $language ISO 639-1 language code
	 * @return string
	 */
	private function localise ($key, $language) {
		$result = '';
		
		$filePath = t3lib_div::getFileAbsFileName('EXT:' . NKWGOKExtKey . '/pi1/locallang.xml');
		if (!$this->localisation) {
			if (t3lib_div::int_from_ver(TYPO3_version) >= 4006000) {
				/**
				 * In TYPO3 >=4.6 t3lib_l10n_parser_Llxml is recommended for reading
				 * localisations.
				 *
				 * The returned $localisation seems to have the following structure:
				 * array('languageKey' => array('stringKey' => array(array('target' => 'localisedString'))))
				 * Only the requested languageKey seems to be present and the innermost
				 * array can also contain a 'source' key.
				 */
				$parser = t3lib_div::makeInstance('t3lib_l10n_parser_Llxml');
				$this->localisation = $parser->getParsedData($filePath, $language);
			}
			else {
				/**
				 * In TYPO3 <4.6 use t3lib_div::readLLXMLfile.
				 *
				 * The returned $localisation has the following structure:
				 * array('languageKey' => array('stringKey' => 'localisedString'))
				 * It seems to contain languageKeys for all localisations in the XML file.
				 */
				$this->localisation = t3lib_div::readLLXMLfile($filePath, $language);
			}
		}

		$myLanguage = $language;
		if (!array_key_exists($language, $this->localisation)) {
			$myLanguage = 'default';
		}
		
		if (array_key_exists($key, $this->localisation[$myLanguage])) {
			$result = $this->localisation[$myLanguage][$key];
		}
		else {
			// Return the original key in upper case if we don’t find a localisation.
			$result = strtoupper($key);
		}

		// In TYPO3 >=4.6 $result is an array. Extract the relevant string from that.
		if (is_array($result)) {
			$result = $result[0]['target'];
		}

		return $result;
	}



	/**
	 * Return GOK name for display.
	 *
	 * Use English if the language code is 'en' and German otherwise.
	 *
	 * Some GOK names end with a super-subject indicator enclosed in { }.
	 * This is helpful when viewing the subject name on its own but is redundant
	 * when viewed inside the subject hierarchy. The parameter $simplify = True
	 * removes that indicator.
	 *
	 * @author Sven-S. Porst <porst@sub.uni-goettingen.de>
	 * @param Array $gokRecord
	 * @param string $language ISO-639-1 language code as used by Typo3 [defaults to 'de']
	 * @param Boolean $simplify should the trailing {…} be removed? [defaults to False]
	 * @return string
	 */
	private function GOKName($gokRecord, $language='de', $simplify = False) {
		$displayName = $gokRecord['descr'];

		if ($language == 'en') {
			$englishName = $gokRecord['descr_en'];

			if ($englishName) {
				$displayName = $englishName;
			}
		}

		// Remove trailing ' - Allgemein- und Gesamtdarstellungen'
		// Remove trailing super-subject designator in { }
		if ($simplify) {
			$displayName = preg_replace("/ - Allgemein- und Gesamtdarstellungen$/", "", $displayName);
			$displayName = preg_replace("/( \{.*\})$/", "", $displayName);
		}
		return trim($displayName);
	}



	/**
	 * Returns GOK records for the children of a given PPN, ordered by GOK.
	 *
	 * @param string $parentPPN
	 * @param Boolean $includeParent if True, the parent item is included
	 * @return Array of GOK records of the $parentPPN’s children
	 */
	private function getChildren($parentPPN, $includeParent = False) {
		$parentEscaped = $GLOBALS['TYPO3_DB']->fullQuoteStr($parentPPN, NKWGOKQueryTable);
		$includeParentSelectCondition = '';
		if ($includeParent) {
			$includeParentSelectCondition = ' OR ppn = ' . $parentEscaped;
		}
		$whereClause = '(parent = ' . $parentEscaped . $includeParentSelectCondition . ') AND statusID = 0';
		$queryResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					NKWGOKQueryFields,
					NKWGOKQueryTable,
					$whereClause,
					'',
					'hierarchy,gok ASC',
					'');

		$children = Array();
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($queryResult)) {
			$children[] = $row;
		}

		return $children;
	}



	/**
	 * Appends two Opac search links to $container, one for shallow search and
	 * one for deep search. One of them will be hidden by CSS.
	 *
	 * @author Sven-S. Porst
	 * @param Array $GOK GOK record
	 * @param DOMDocument $doc document used to create the resulting element
	 * @param string $language ISO 639-1 language code
	 * @param DOMElement $container the link elements are appended to
	 */
	private function appendOpacLinksTo ($GOK, $doc, $language, $container) {
		$opacLinkElement = $this->OPACLinkElement($GOK, $doc, $language, True);
		if ($opacLinkElement) {
			$container->appendChild($doc->createTextNode(' '));
			$container->appendChild($opacLinkElement);
		}

		$opacLinkElement = $this->OPACLinkElement($GOK, $doc, $language, False);
		if ($opacLinkElement) {
			$container->appendChild($doc->createTextNode(' '));
			$container->appendChild($opacLinkElement);
		}
	}



	/**
	 * Returns DOMElement with complete markup for linking to the OPAC entry.
	 * The link text indicates the number of results if it is known.
	 *
	 * @author Sven-S. Porst
	 * @param Array $GOKData GOK record
	 * @param DOMDocument $doc document used to create the resulting element
	 * @param string $language ISO 639-1 language code
	 * @param Boolean $deepSearch
	 * @return DOMElement
	 */
	private function OPACLinkElement ($GOKData, $doc, $language, $deepSearch) {
		$opacLink = Null;
		$hitCount = $GOKData['hitcount'];
		$useDeepSearch = $deepSearch && ($GOKData['totalhitcount'] > 0);
		if ($useDeepSearch === True) {
			$hitCount = $GOKData['totalhitcount'];
		}
		$URL = $this->opacGOKSearchURL($GOKData, $language, $deepSearch);
		if ($hitCount != 0 && $URL) {
			$opacLink = $doc->createElement('a');
			$opacLink->setAttribute('href', $URL);
			$titleString = '';
			if ($useDeepSearch === True && $GOKData['childcount'] != 0) {
				$titleString = $this->localise('Bücher zu diesem und enthaltenen Themengebieten im Opac anzeigen', $language);
			}
			else {
				$titleString = $this->localise('Bücher zu genau diesem Thema im Opac anzeigen', $language);
			}
			$opacLink->setAttribute('title', $titleString);

			// Question: Is '_blank' a good idea?
			$opacLink->setAttribute('target', '_blank');
			if ($hitCount > 0) {
				// we know the number of results: display it
				$numberString = number_format($hitCount, 0, $this->localise('decimal separator', $language), $this->localise('thousands separator', $language));
				$opacLink->appendChild($doc->createTextNode(sprintf($this->localise('%s Treffer anzeigen', $language), $numberString)));
			}
			else {
				// we don't know the number of results: display a general text
				$opacLink->appendChild($doc->createTextNode($this->localise('Treffer anzeigen', $language)));
			}

			$linkClass= 'opacLink ' . (($deepSearch === True) ? 'deep' : 'shallow');
			$opacLink->setAttribute('class', $linkClass);
		}

		return $opacLink;
	}



	/**
	 * Returns URL string for an Opac Search.
	 * If $deepSearch is false, the search query stored in $GOKData is used.
	 * If $deepSearch is true, a deep hierarchical search for records related
	 * to the GOK Normsatz PPN is used
	 * If the record did not originate from Opac, Null is returned.
	 *
	 * @author Sven-S. Porst
	 * @param Array $GOKData GOK record
	 * @param string $language ISO 639-1 language code
	 * @param Boolean $deepSearch
	 * @return string|Null URL
	 */
	private function opacGOKSearchURL($GOKData, $language, $deepSearch) {
		$GOKSearchURL = Null;

		$conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['nkwgok']);
		$picaLanguageCode = ($language === 'en') ? 'EN' : 'DU';
		$GOKSearchURL = $conf['opacBaseURL'] . 'LNG=' . $picaLanguageCode;

		if ($deepSearch === True && $GOKData['fromopac'] == 1 && $GOKData['ppn'] !== 'GOK-Root') {
			// Use special command to do the hierarchical search for records related
			// to the Normsatz PPN.
			$GOKSearchURL .= '/EPD?PPN=' . $GOKData['ppn'] . '&FRM=';
		}
		else if ($GOKData['search']) {
			// Convert CCL string to Opac-style search string and escape.
			$searchString = urlencode(str_replace('=', ' ', $GOKData['search']));
			$GOKSearchURL .= '/REC=1/CMD?ACT=SRCHA&IKT=1016&SRT=YOP&TRM=' . $searchString;
		}
		else {
			$GOKSearchURL = Null;
		}

		return $GOKSearchURL;
	}


	
	/**
	 * Helper function to add our default stylesheet or the one at the path
	 * set up in Extension Manager configuration to the page’s head.
	 * 
	 * @author Sven-S. Porst
	 * @return void 
	 */
	private function addStylesheet () {
		$nkwgokGlobalConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['nkwgok']);
		$cssPath = $nkwgokGlobalConf['CSSPath'];
		if (!$cssPath) {
			$cssPath = 'EXT:' . $this->extKey . '/res/nkwgok.css';
		}
		
		$GLOBALS['TSFE']->pSetup['includeCSS.'][$this->extKey] = $cssPath;
	}



	/**
	 * Return DOMDocument representing the tree set up in the given configuration.
	 *
	 * This is the only function called to create the tree on the web page.
	 *
	 * The $conf array needs to contain:
	 * - an string element 'gok' which can either be 'all' (to display the
	 *		complete GOK tree) or a GOK string of the node to be used as the
	 *		root of the tree
	 * - an array element 'getVars' with:
	 *   * an array element 'expand'. Each of that array’s elements are PPNs of
	 *     the GOK elements displaying their child elements
	 *   * an array element 'showGOKID' indicating whether GOK IDs are shown or hidden
	 *
	 * @author Sven-S. Porst
	 * @param Array $conf
	 * @return DOMDocument
	 */
	public function GOKTree ($conf) {
		$language = $GLOBALS['TSFE']->lang;
		$objectID = $this->cObj->data['uid'];

		$doc = DOMImplementation::createDocument();
		$this->addGOKTreeJSToElement($doc, $doc, $language, $objectID);

		$this->addStylesheet();

		// Get start node.
		$firstNodeCondition = "gok LIKE " . $GLOBALS['TYPO3_DB']->fullQuoteStr($conf['gok'], NKWGOKQueryTable) . ' AND statusID = 0';
		$queryResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					NKWGOKQueryFields,
					NKWGOKQueryTable,
					$firstNodeCondition,
					'',
					'gok ASC',
					'');
		$GOKs = Array();
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($queryResult)) {
			$GOKs[] = $row;
		}

		foreach ($GOKs as $GOK) {
			$container = $doc->createElement('div');
			$doc->appendChild($container);

			$containerClasses = Array('gokContainer', 'tree');
			if (!$conf['getVars']['showGOKID']) {
				$containerClasses[] = 'hideGOKID';
			}
			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_nkwgok_pi1.']['shallowSearch'] == 1) {
				$containerClasses[] = 'shallowLinks';
			}
			$container->setAttribute('class', implode(' ', $containerClasses));

			$topElement = $this->appendGOKTreeItem($doc, $container, 'span', $GOK, $language, $objectID, $conf['getVars']['expand'], '', 1, False);
			$topElement->setAttribute('class', 'rootNode');

			$this->appendGOKTreeChildren($GOK['ppn'], $doc, $container, $language, $objectID, $conf['getVars']['expand'], '', 1);
		}

		return $doc;
	}



	/**
	 * Helper function to insert JavaScript for the GOK Tree into the passed
	 * $element.
	 *
	 * It seems we need to pass the DOMDocument here as using $element->ownerDocument
	 * doesn't seem to work if $element is the DOMDocument itself.
	 *
	 * @author Sven-S. Porst
	 * @param DOMElement $element the <script> tag is inserted into
	 * @param DOMDocument $doc the containing document
	 * @param string $language ISO 369-1 language code
	 * @param string $objectID ID of Typo3 content object
	 * @return void
	 */
	private function addGOKTreeJSToElement ($element, $doc, $language, $objectID) {
		$scriptElement = $doc->createElement('script');
		$doc->appendChild($scriptElement);
		$scriptElement->setAttribute('type', 'text/javascript');

		$js = "
		function swapTitles" . $objectID . " (element) {
			var jQElement = jQuery(element);
			var otherTitle = jQElement.attr('alttitle');
			jQElement.attr('alttitle', jQElement.attr('title'));
			jQElement.attr('title', otherTitle);
		}
		function expandGOK" . $objectID . " (id) {
			var link = jQuery('#openCloseLink-" . $objectID ."-' + id);
			var plusMinus = jQuery('.plusMinus', link);
			swapTitles" . $objectID . "(link);
			plusMinus.text('[*]');
			var functionText = 'hideGOK" . $objectID . "(\"' + id + '\");return false;';
			link[0].onclick = new Function(functionText);
			jQuery.get("
				. "'" . t3lib_div::getIndpEnv('TYPO3_SITE_URL') . "index.php',
				{'eID': '" . NKWGOKExtKey . "', "
				. "'tx_" . NKWGOKExtKey . "[language]': '" . $language . "', "
				. "'tx_" . NKWGOKExtKey . "[expand]': id, "
				. "'tx_" . NKWGOKExtKey . "[style]': 'tree', "
				. "'tx_" . NKWGOKExtKey . "[objectID]': '" . $objectID . "'},
				function (html) {
					plusMinus.text('[-]');
					jQuery('#c" . $objectID . "-' + id).append(html);
				}
			);
		};
		function hideGOK". $objectID . " (id) {
			jQuery('#ul-" . $objectID . "-' + id).remove();
			var link = jQuery('#openCloseLink-" . $objectID . "-' + id);
			jQuery('.plusMinus', link).text('[+]');
			swapTitles" . $objectID . "(link);
			var	functionText = 'expandGOK" . $objectID . "(\"' + id + '\");return false;';
			link[0].onclick = new Function(functionText);
		};
";
		$scriptElement->appendChild($doc->createTextNode($js));
	}



	/**
	 * Looks up child elements for the given $parentPPN,
	 * creates a list with markup for them
	 * and adds them to the given $container element inside $doc,
	 * taking into account which parent elements are configured to display their
	 * children.
	 *
	 * @author Nils K. Windisch
	 * @author Sven-S. Porst
	 * @param string $parentPPN
	 * @param DOMDocument $doc document used to create the resulting element
	 * @param DOMElement $container the created markup is appended to (needs to be a child element of $doc)
	 * @param string $language ISO 639-1 language code
	 * @param string $objectID ID of Typo3 content object
	 * @param Array $expandInfo information which PPNs need to be expanded
	 * @param string $expandMarker list of PPNs of open parent elements, separated by '-'
	 * @param int $autoExpandLevel automatically expand subentries if they have at most this many child elements
 	 * @return void
	 * */
	private function appendGOKTreeChildren($parentPPN, $doc, $container, $language, $objectID, $expandInfo, $expandMarker, $autoExpandLevel) {
		$GOKs = $this->getChildren($parentPPN, True);
		if (sizeof($GOKs) > 1) {
			$ul = $doc->createElement('ul');
			$container->appendChild($ul);
			$ul->setAttribute('id', 'ul-' . $objectID . '-' . $parentPPN);

			/* The first item in the array is the parent element. Fetch it
			 * and
			 */
			$firstGOK = array_shift($GOKs);
			if ($firstGOK['hitcount'] > 0) {
				$firstGOK['descr'] = $this->localise('Allgemeines', $language);
				$this->appendGOKTreeItem($doc, $ul, 'li', $firstGOK, $language, $objectID, $expandInfo, $expandMarker, $autoExpandLevel, False, 'general-items-node');
			}

			foreach ($GOKs as $GOK) {
				/* Do not display the GOK item if
				 * 1. it has no child elements and
				 * 2. it is known to have no matching hits
				 */
				if ($GOK['hitcount'] != 0 || $GOK['childcount'] != 0) {
					$this->appendGOKTreeItem($doc, $ul, 'li', $GOK, $language, $objectID, $expandInfo, $expandMarker, $autoExpandLevel);
				}
			} // end foreach ($GOKs as $GOK)
		}
	}


	
	/**
	 * Appends a single GOK item child element of typ3 $elementName
	 * to the element $container inside $doc and returns it.
	 * 
	 * @author Sven-S. Porst
	 * @param DOMDocument $doc document used to create the resulting element
	 * @param DOMElement $container the created markup is appended to (needs to be a child element of $doc)
	 * @param string $elementName name of the element to insert into $container
	 * @param Array $GOK
	 * @param string $language ISO 639-1 language code
	 * @param string $objectID ID of Typo3 content object
	 * @param Array $expandInfo information which PPNs need to be expanded
	 * @param string $expandMarker list of PPNs of open parent elements, separated by '-' [defaults to '']
	 * @param int $autoExpandLevel automatically expand subentries if they have at most this many child elements [defaults to 0]
	 * @param Boolean $isInteractive whether the element can be an expandable part of the tree and should have dynamic links [defaults to True]
	 * @param string|Null $extraClass class added to the appended links [defaults to Null]
	 * @return DOMElement
	 */
	private function appendGOKTreeItem ($doc, $container, $elementName, $GOK, $language, $objectID, $expandInfo, $expandMarker, $autoExpandLevel, $isInteractive = True, $extraClass = Null) {
		$PPN = $GOK['ppn'];
		$expand = $PPN;

		if ($expandMarker != '') {
			$expand = $expandMarker . '-' . $PPN;
		}

		/* Display in each list item:
		 * 1. Expand xor collapse link if there are child elements depending on the expanded state
		 * 2. The linked name
		 * 3. If the item has child elements and is expanded, the list of child elements
		 */
		$item = $doc->createElement($elementName);
		$container->appendChild($item);
		$item->setAttribute('id', 'c' . $objectID . '-' . $PPN);

		$openLink = $doc->createElement('a');
		$openLink->setAttribute('id', 'openCloseLink-' . $objectID . '-' . $PPN);
		$item->appendChild($openLink);

		$control = $doc->createElement('span');
		$openLink->appendChild($control);
		$openLinkClass = 'plusMinus';
		if ($isInteractive !== True) {
			$openLinkClass .= ' nkwgok-invisible';
		}
		$control->setAttribute('class', $openLinkClass);

		$GOKIDSpan = $doc->createElement('span');
		$GOKIDSpan->setAttribute('class', 'GOKID');
		$GOKIDSpan->appendChild($doc->createTextNode($GOK['gok']));

		$GOKNameSpan = $doc->createElement('span');
		$GOKNameSpan->setAttribute('class', 'GOKName');
		$GOKNameSpan->appendChild($doc->createTextNode($this->GOKName($GOK, $language, True)));

		$openLink->appendChild($doc->createTextNode(' '));
		$openLink->appendChild($GOKIDSpan);
		$openLink->appendChild($doc->createTextNode(' '));
		$openLink->appendChild($GOKNameSpan);
		$this->appendOpacLinksTo($GOK, $doc, $language, $item);

		$itemClass = '';
		if ($extraClass !== Null) {
			$itemClass = $extraClass . ' ';
		}

		$buttonText = '   ';
		if ($isInteractive === True) {
			// Careful: These are three non-breaking spaces to get better alignment.
			if ($GOK['childcount'] > 0) {
				$JSCommand = '';
				$noscriptLink = '#';
				$mainTitle = $GOK['childcount'] . ' ' . $this->localise('Unterkategorien anzeigen', $language);
				$alternativeTitle = $this->localise('Unterkategorien ausblenden', $language);

				if ( ($expandInfo && in_array($PPN, $expandInfo)) || $GOK['childcount'] <= $autoExpandLevel) {
					$itemClass .= 'close';
					$JSCommand = 'hideGOK' . $objectID;
					$buttonText = '[-]';
					$tmpTitle = $mainTitle;
					$mainTitle = $alternativeTitle;
					$alternativeTitle = $tmpTitle;
					$noscriptLink = t3lib_div::linkThisUrl(t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'),
							array('tx_' . NKWGOKExtKey . '[expand]' => $expandMarker) );

					// recursively call self to get child UL
					$this->appendGOKTreeChildren($PPN, $doc, $item, $language, $objectID, $expandInfo, $expand, $autoExpandLevel);
				}
				else {
					$itemClass .= 'open';
					$JSCommand = 'expandGOK' . $objectID;
					$buttonText = '[+]';
					$noscriptLink = t3lib_div::linkThisUrl(t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'),
							array('tx_' . NKWGOKExtKey . '[expand]' => $expand, 'no_cache' => 1) )
							. '#c' . $PPN;
				}

				$openLink->setAttribute('onclick',  $JSCommand . '("' . $PPN . '");return false;');
				$openLink->setAttribute('href', $noscriptLink);
				$openLink->setAttribute('rel', 'nofollow');
				$openLink->setAttribute('title', $mainTitle);
				$openLink->setAttribute('alttitle', $alternativeTitle);
			}
		}

		$item->setAttribute('class', $itemClass);

		$control->appendChild($doc->createTextNode($buttonText));
		
		return $item;
	}



	/**
	 * Create DOMDocument for AJAX return value and fill it with markup for the
	 * parent PPN and language given.

	 * @author Sven-S. Porst
	 * @param string $parentPPN
	 * @param string $language ISO 639-1 language code
	 * @param string $objectID ID of Typo3 content object
	 * @return DOMDocument
	 * */
	public function AJAXGOKTreeChildren ($parentPPN, $language, $objectID) {
		$doc = DOMImplementation::createDocument();
		
		$this->appendGOKTreeChildren($parentPPN, $doc, $doc, $language, $objectID, Array($parentPPN), '', 1)->firstChild;

		return $doc;
	}



	/**
	 * Returns markup for GOK menus using the parameters passed in $conf.
	 *
	 * @author Sven-S. Porst
	 * @param Array $conf
	 * @return DOMElement containing the markup for a menu
	 */
	public function GOKMenus ($conf) {
		$language = $GLOBALS['TSFE']->lang;
		$objectID = $this->cObj->data['uid'];
		
		$doc = DOMImplementation::createDocument();
		$this->addGOKMenuJSToElement($doc, $doc, $language, $objectID);

		$this->addStylesheet();

		// Create the form and insert the first menu.
		$container = $doc->createElement('div');
		$doc->appendChild($container);
		$container->setAttribute('class', 'gokContainer menu');
		$form = $doc->createElement('form');
		$container->appendChild($form);
		$form->setAttribute('class', 'gokMenuForm no-JS');
		$form->setAttribute('method', 'get');
		$form->setAttribute('action', $this->pi_getPageLink($GLOBALS['TSFE']->id));
		
		$pageID = $doc->createElement('input');
		$form->appendChild($pageID);
		$pageID->setAttribute('type', 'hidden');
		$pageID->setAttribute('name', 'id');
		$pageID->setAttribute('value', $GLOBALS['TSFE']->id);
		
		$firstNodeCondition = "gok LIKE " . $GLOBALS['TYPO3_DB']->fullQuoteStr($conf['gok'], NKWGOKQueryTable) . ' AND statusID = 0';
		// run query and collect result
		$queryResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					NKWGOKQueryFields,
					NKWGOKQueryTable,
					$firstNodeCondition,
					'',
					'gok ASC',
					'');

		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($queryResult)) {
			$this->appendGOKMenuChildren($row['ppn'], $doc, $form, $language, $objectID, $conf['getVars'], 2);
		}

		$button = $doc->createElement('input');
		$button->setAttribute('type', 'submit');
		$form->appendChild($button);

		return $doc;
	}


	
	/**
	 * Helper function to insert JavaScript for the GOK Menu into the passed
	 * $element.
	 *
	 * It seems we need to pass the DOMDocument here as using $element->ownerDocument
	 * doesn't seem to work if $element is the DOMDocument itself.
	 *
	 * @author Sven-S. Porst
	 * @param DOMElement $element the <script> tag is inserted into
	 * @param DOMDocument $doc the containing document
	 * @param string $language ISO 639-1 language code
	 * @param string $objectID ID of Typo3 content object
	 */
	private function addGOKMenuJSToElement ($element, $doc, $language, $objectID) {
		$scriptElement = $doc->createElement('script');
		$element->appendChild($scriptElement);
		$scriptElement->setAttribute('type', 'text/javascript');

		$js = "
		jQuery(document).ready(function() {
			jQuery('.gokMenuForm input[type=\'submit\']').hide();
		});
		
		function GOKMenuSelectionChanged" . $objectID . " (menu) {
			var selectedOption = menu.options[menu.selectedIndex];
			jQuery(menu).nextAll().remove();
			if (selectedOption.getAttribute('haschildren') && !selectedOption.getAttribute('isautoexpanded')) {
				newMenuForSelection" . $objectID . "(selectedOption);
			}
			if (selectedOption.value != 'pleaseselect') {
				jQuery('option[value=\"pleaseselect\"]', menu).remove();
			}
			startSearch" . $objectID . "(selectedOption);
		}

		function newMenuForSelection" . $objectID . " (option) {
			var URL = location.protocol + '//' + location.host + location.pathname;
			var PPN = option.value;
			var level = parseInt(option.parentNode.getAttribute('level')) + 1;
			var parameters = location.search.replace(/^\?/, '') + '&tx_" . NKWGOKExtKey . "[expand]=' + PPN
				+ '&tx_" . NKWGOKExtKey . "[language]=" . $language . "&eID=" . NKWGOKExtKey . "'
				+ '&tx_" . NKWGOKExtKey . "[level]=' + level
				+ '&tx_" . NKWGOKExtKey . "[style]=menu'
				+ '&tx_" . NKWGOKExtKey . "[objectID]=" . $objectID . "';

			jQuery(option.parentNode).nextAll().remove();
			var newSelect = document.createElement('select');
			var jNewSelect = jQuery(newSelect);
			newSelect.setAttribute('level', level);
			jNewSelect.hide();
			option.form.appendChild(newSelect);
			jNewSelect.slideDown('fast');
			var loadingOption = document.createElement('option');
			newSelect.appendChild(loadingOption);
			loadingOption.appendChild(document.createTextNode('" . $this->localise('Laden ...', $language) . "'));
			var downloadFinishedFunction = function (HTML) {
				jNewSelect.empty();
				var jHTML = jQuery(HTML);
				var newOptions = jQuery('option, optgroup', jHTML);
				if (newOptions.length > 0) {
					newOptions[0].setAttribute('query', option.getAttribute('query'));
				}
				jNewSelect.attr('onchange', jHTML.attr('onchange'));
				jNewSelect.attr('title', jHTML.attr('title'));
				jNewSelect.append(newOptions);
				newSelect.selectedIndex = 0;
			};
			jQuery.get(URL, parameters, downloadFinishedFunction);
		}
		function startSearch" . $objectID . " (option) {
			nkwgokMenuSelected(option);
		}
";
		$scriptElement->appendChild($doc->createTextNode($js));
	}

	

	/**
	 * Looks up child elements for the given $parentPPN, creates DOM elements
	 * for a popup menu containing the child elements and adds them to the
	 * given $container element inside $doc, taking into account which
	 * menu items are configured to be selected.
	 *
	 * Also tries to include short (as in at most the length of $autoExpandLevel)
	 * submenus in higher level menus, adding an indent to their titles.
	 *
	 * @author Sven-S. Porst
	 * @param string $parentPPN
	 * @param DOMDocument $doc document used to create the resulting element
	 * @param DOMElement $container the created markup is appended to (needs to be a child element of $doc). Is expected to be a <select> element if the $autoExpandStep paramter is not 0 and a <form> element otherwise.
	 * @param string $language ISO 639-1 language code
	 * @param string $objectID ID of Typo3 content object
	 * @param Array $getVars entries for keys tx_nkwgok[expand-#] for an integer # are the selected items on level #
	 * @param int $autoExpandLevel automatically expand subentries if they have at most this many child elements [defaults to 0]
	 * @param int $level the depth in the menu hierarchy [defaults to 0]
	 * @param int $autoExpandStep the depth of auto-expansion [defaults to 0]
	 */
	private function appendGOKMenuChildren($parentPPN, $doc, $container, $language, $objectID, $getVars, $autoExpandLevel = 0, $level = 0, $autoExpandStep = 0) {
		$GOKs = $this->getChildren($parentPPN);
		if (sizeof($GOKs) > 0) {
			if ( (sizeof($GOKs) <= $autoExpandLevel) && ($level != 0) && $autoExpandStep == 0 ) {
				// We are auto-expanded, so throw away the elements, as they are already present in the previous menu
				$GOKs = Array();
			}

			// When auto-expanding, continue using the previous <select>
			// Element which should be passed to us as $container.
			$select = $container;
			
			if ($autoExpandStep == 0) {
				// Create the containing <select> when we’re not auto-expanding.
				$select = $doc->createElement('select');
				$container->appendChild($select);
				$select->setAttribute('id', 'select-' . $objectID . '-' . $parentPPN);
				$select->setAttribute('name', 'tx_' . NKWGOKExtKey . '[expand-' . $level . ']');
				$select->setAttribute('onchange', 'GOKMenuSelectionChanged' . $objectID . '(this);');
				$select->setAttribute('title', $this->localise('Fachgebiet auswählen', $language) . ' ('
						. $this->localise('Ebene', $language) . ' ' . ($level + 1) . ')');
				$select->setAttribute('level', $level);

				// add dummy item at the beginning of the menu
				if ($level == 0) {
					$option = $doc->createElement('option');
					$select->appendChild($option);
					$option->appendChild($doc->createTextNode($this->localise('Bitte Fachgebiet auswählen:', $language) ));
					$option->setAttribute('value', 'pleaseselect');
				}
				else {
					/* Add general menu item(s).
					 * A menu item searching for all subjects beneath the selected one in the 
					 * hierarchy and one searching for records matching exactly the subject selected.
					 * The latter case is only expected to happen for subjects coming from Opac GOK
					 * records.
					 */
					$option = $doc->createElement('option');
					$select->appendChild($option);
					$label = '';
					if ($GOKs[0]['fromopac']) {
						$label = 'Treffer für diese Zwischenebene zeigen';
					}
					else {
						$label = 'Treffer aller enthaltenen Untergebiete zeigen';
					}
					$option->appendChild($doc->createTextNode($this->localise($label, $language)));
					$option->setAttribute('value', 'withchildren');
					if (!$getVars['expand-' . $level]) {
						$option->setAttribute('selected', 'selected');
					}

					if (count($GOKs) > 0) {
						$optgroup = $doc->createElement('optgroup');
						$select->appendChild($optgroup);
					}
				}
			}

			foreach ($GOKs as $GOK) {
				$PPN = $GOK['ppn'];

				$option = $doc->createElement('option');
				$select->appendChild($option);
				$option->setAttribute('value', $PPN);
				$option->setAttribute('query', $GOK['search']);
				// Careful: non-breaking spaces used here to create in-menu indentation
				$menuItemString = str_repeat('   ', $autoExpandStep) . $this->GOKName($GOK, $language, True);
				if ($GOK['childcount'] > 0) {
					$menuItemString .= $this->localise(' ...', $language);
					$option->setAttribute('hasChildren', $GOK['childcount']);
				}
				$option->appendChild($doc->createTextNode($menuItemString));
				if (($GOK['childcount'] > 0) && ($GOK['childcount'] <= $autoExpandLevel)) {
					$option->setAttribute('isAutoExpanded', '');
					$this->appendGOKMenuChildren($PPN, $doc, $select, $language, $objectID, $getVars, $autoExpandLevel, $level, $autoExpandStep + 1);
				}

				if ( $PPN == $getVars['expand-' . $level] ) {
					// this item should be selected and the next menu should be added
					$option->setAttribute('selected', 'selected');
					$this->appendGOKMenuChildren($PPN, $doc, $container, $language, $objectID, $getVars, $autoExpandLevel, $level + 1);
					// remove the first/default item of the menu if we have a selection already
				}
			}
		}
	}


	
	/**
	 * Create DOMDocument for AJAX return value and fill it with markup for the
	 * $parentPPN, $level and $language given.
	 *
	 * @author Sven-S. Porst
 	 * @param string $parentPPN
	 * @param int $level the depth in the menu hierarchy [defaults to 0]
	 * @param string $language ISO 639-1 language code
	 * @param string $objectID ID of Typo3 content object
	 * @return <type>
	 */
	public function AJAXGOKMenuChildren ($parentPPN, $level, $language, $objectID) {
		$doc = DOMImplementation::createDocument();
		$this->appendGOKMenuChildren($parentPPN, $doc, $doc, $language, $objectID, Array(), 2, $level);

		return $doc;
	}

}

?>
