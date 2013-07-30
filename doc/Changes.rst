Version History
===============

2013-07-30 3.4.1 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* compatible with jQuery ≥1.9

2013-02-05 3.4.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* remove explicit coding for GOK and BRK root nodes; they are represented by authority records now
	* build subtrees in FlexForm using PPN, not notation
	* convert Readme and Changes files from Markdown to reST

2012-12-20 3.3.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* re-implement configuration for loading remote CSV files

	  * do not use TypoScript which is attached to a page
	  * store URLs globally in extension configuration

2012-12-20 3.2.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* fix bug that prevented the correct import of subject information from CSV files only on some systems
	* ensure tree style is chosen when no display mode is selected in the FlexForm
	* Rename ChangeLog file
	* add fake Star Office documentation

2012-12-19 3.1.1 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* fix bug that lost the German subject name when importing from CSV

2012-12-19 3.1.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Add ability to handle additional/alternate description for a subject name

	  * displayed in the tree elements’ tooltips
	  * filled with strings from Pica 044F when $S is g
	  * in both German (default) and English (when $L eng)

	* use XPath for parsing tag 044F (the previous method failed on repeated tags)
	* switch XML parsing on database import to XPath, drop the previously used custom function
	* change field name for the notation from »gok« to »notation«

2012-12-12 3.0.1 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Fix spelling of Band-Realkatalog label

2012-12-11 3.0.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Add support for BRK authority records (Pica type »Tov«)

	  * download the records and their corresponding hit counts

	* change naming scheme for downloaded XML files
	* default to tree style if no style is set in the FlexForm
	* refactoring to reduce memory footprint in database import scheduler task
	* introduce constants for repeatedly used strings
	* table change to store the record type instead of the »fromopac« flag
	* support hit counts for MSC matches from CSV files
	* take into account the $4 subfield when determining a record’s parent
	* avoid a few warning log messages coming from array functions
	* improve strings used in the backend interface
	* workaround for double-escaping bug in Pica XML output

2012-09-19 2.2.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Generalise hitcount analysis to enable recursive hitcounts for MSC and other classification systems

2012-08-06 2.1.1 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Fix jQuery vs IE problem with animations in menu display style
	* Fix HTML vs JavaScript escaping problem for … in menu display style

2012-07-27 2.1.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Change field names in Tev records according to the GND format
	* Add ability to start the tree with more than one element at the root level
	* Add option to omit subjects whose code ends with »XXX« (Flexform)

2012-06-01 2.0.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Clean up class structure.
	* Add option for column style display.
	* Reduce superfluous CSV file imports.
	* Drop TYPO3 4.5 compatibility

2012-02-22 1.6.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Make number of inline menu child elements configurable through the `menuInlineThreshold` TypoScript setting.

2012-01-27 1.5.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Ignore hierarchy level in Pica field 009B $a and compute the value ourselves. We depend on this data being correct and it may be incorrect, causing an infinite loop.
	* Refactor functions and variables.
	* Split XML files generated from CSV files into chunks of 500 records (to avoid hitting the 128MB RAM limit for PHP)
	* CHANGE CSV DATA FORMAT: remove columns 2 and 3
	* Fix links to OPAC (enclose search terms in quotation marks)

2012-01-23 1.4.0 Ingo Pfennigstorf <pfennigstorf@sub.uni-goettingen.de>
	* add nofollow attribute to tree expansion/collapsing links to reduce overzealous spidering

2011-12-13 1.3.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* improve usage of deep OPAC links (introduce a 'General' element for the records associated to parent nodes)
	* fix bug with importing introduced in 1.2.1

2011-11-25 1.2.1 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* switch default OPAC URL to https
	* only retrieve records with statusID = 0 in database queries to avoid duplicates
	* use quotation marks in term used for index scans
	* deal with differing data structure returned by TYPO3 4.6’s t3lib_div::readLLXMLfile()
	* fix incorrect processing of the shallowSearch TypoScript setting

2011-11-24 1.2.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* vastly reduce downtime due to database update (batch add new records, switch with old records after import has finished) REQUIRES DATABASE SCHEMA UPDATE
	* fix localisation problem in TYPO3 4.6
	* extend timeout for long scheduler actions to 20 minutes
	* add searchFields to ext_tables.php

2011-11-11 1.1.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* adapt hitcount usage to current OPAC indexing
	* remove support for old-school subject tree style
	* more generic tree item creation, allowing links for the root node
	* improve default look of multiline items and GOK ID display

2011-09-01 1.0.0 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* final tweaks, we’ll start using this

2011-08-23 0.9.13 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* compute total hit counts for OPAC LKLs
	* add total hitcounts to database
	* add link to full subject hierarchy to tree output
	* add plugin.tx_nkwgok_pi1.shallowSearch setting to toggle between deep and shallow searches

2011-08-02 0.9.11 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Reduce logging
	* Cleanup

2011-08-01 0.9.10 Ingo Pfennigstorf <pfennigstorf@sub.uni-goettingen.de>
	* Optimized Scheduler Task for converting CSV Data with an optional page id
	* Moved scheduler tasks to a seperate directory

2011-05-29 0.9.9 Ingo Pfennigstorf <pfennigstorf@sub.uni-goettingen.de>
	* Added hitcounts for various fields
	* Added configuration for Scheduler Tasks in TypoScript

2011-05-19 0.9.8 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* add field fromopac to database to reliably spot records originating from the OPAC
	* use lowercase 'lkl' search key instead of 'LKL' for OPAC GOK records

2011-05-15 0.9.7 Ingo Pfennigstorf <pfennigstorf@sub.uni-goettingen.de>
	* Added TCA definition for the new Tag field

2011-05-05 0.9.6 Sven-S. Porst <porst@sub.uni-goettigen.de>
	* Change the query format in the search column of the data table from URL-escaped Pica OPAC queries to non-escaped CCL-queries

2011-05-02 0.9.5 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* rename 'Convert history' scheduler task to 'Convert CSV'
	* store XML files in 'xml' subfolder of fileadmin/gok instead of 'lkl' subfolder
	* change data tree, so we can have other trees besides the main GOK tree (e.g. for special Neuerwerbungen or Guide subject lists
	* add backend configuration for hiding GOK-IDs in tree view
	* add 'tags' field from column 8 in CSV files

2011-03-30 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Add ID of containing TYPO3 object to DOM IDs, so we can use several GOK-displays on the same web page

2011-03-24 0.9 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* Add loading of OPAC data as a scheduler job
	* Add conversion of History CSV data as a scheduler job
	* Add a scheduler job to perform all data fetching/conversion/import in one go
	* improve documentation

2011-03-14 0.8 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* introduce two display styles (old vs. improved) for the tree
	* store all search queries _completely_ in the database
	* remove options for different query styles
	* simplify extension search setup: only provide a single URL
	* move CSS into separate files
	* remove superfluous dependency on nkwlib

2011-02-28 - 0.7 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* provide a display style using menus
	* download hit counts from OPAC
	* display hit counts in tree style

2011-02-23 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* add child count to database (instead of yes/no information)

2011-02-23 Sven-S. Porst <porst@sub.uni-goettingen.de>
	* major code cleanup
	* single code path for AJAX and non AJAX case
	* correct order of display in non AJAX case

2011-02-11 Ingo Pfennigstorf <pfennigstorf@sub.uni-goettingen.de>
	* Removed Ajax spinner Icon and loading-message
	* Fixed deprecated function ereg-replace
	* Determine whether jQuery nonClonflict Mode shall be used or not
	* Fixed link to index.php

2011-02-10 - 0.0.24 - Sven-S. Porst <porst@sub.uni-goettingen.de>
	* fix reliance on TYPO3 being at document root
	* rework import script to be more reliable/efficient/fast
	* add import of English translations from 044F $a
	* add display of English translations
	* fix quotation errors in JavaScript strings
	* cleaner display of subject names
	* use t3jquery to formalise jQuery dependency

2010-10-22 - 0.0.23 - Ingo Pfennigstorf <pfennigstorf@sub.uni-goettingen.de>
	* jQuery Statusmeldung internationalisiert und in locallang.xml ausgelagert

2010-09-24 - 0.0.22 - Nils K. Windisch <windisch@sub.uni-goettingen.de>
	* UI and AJAX changes
	* TODO: HTML fallback broken... sort of

2010-09-08 - 0.0.21  Nils K. Windisch <windisch@sub.uni-goettingen.de>
	* bug fix in ext_emconf.php

2010-09-02 - 0.0.20 - Nils K. Windisch
	* removed debug output

2010-08-24 - 0.0.18 - Nils K. Windisch
	* lang fix

2010-08-24 - 0.0.17 - Nils K. Windisch
	* Dynamische Flexform liest die oberste Ebene der GOK aus
	* Angabe einer einzelnen GOK als Startpunkt möglich
	* new file: EXT:lib/class.tx_nkwgok_ff.php
