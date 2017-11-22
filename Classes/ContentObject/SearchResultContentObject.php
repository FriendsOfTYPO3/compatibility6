<?php
namespace TYPO3\CMS\Compatibility6\ContentObject;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Search class used for the content object SEARCHRESULT
 * and searching in database tables, typ. "pages" and "tt_content"
 * Used to generate search queries for TypoScript.
 * The class is included from "TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer"
 * based on whether there has been detected content in the GPvar "sword"
 */
class SearchResultContentObject extends \TYPO3\CMS\Frontend\ContentObject\AbstractContentObject
{
    /**
     * @var array
     */
    public $tables = [];

    /**
     * Alternatively 'PRIMARY_KEY'; sorting by primary key
     *
     * @var string
     */
    public $group_by = 'PRIMARY_KEY';

    /**
     * Standard SQL-operator between words
     *
     * @var string
     */
    public $default_operator = 'AND';

    /**
     * @var bool
     */
    public $operator_translate_table_caseinsensitive = true;

    /**
     * case-sensitive. Defines the words, which will be operators between words
     *
     * @var array
     */
    public $operator_translate_table = [
        ['+', 'AND'],
        ['|', 'AND'],
        ['-', 'AND NOT'],
        // english
        ['and', 'AND'],
        ['or', 'OR'],
        ['not', 'AND NOT']
    ];

    /**
     * Contains the search-words and operators
     *
     * @var array
     */
    public $sword_array;

    /**
     * Contains the query parts after processing.
     *
     * @var array
     */
    public $queryParts;

    /**
     * This is set with the foreign table that 'pages' are connected to.
     *
     * @var string
     */
    public $fTable;

    /**
     * How many rows to offset from the beginning
     *
     * @var int
     */
    public $res_offset = 0;

    /**
     * How many results to show (0 = no limit)
     *
     * @var int
     */
    public $res_shows = 20;

    /**
     * Intern: How many results, there was last time (with the exact same searchstring.
     *
     * @var int
     */
    public $res_count;

    /**
     * List of pageIds.
     *
     * @var string
     */
    public $pageIdList = '';

    /**
     * @var string
     */
    public $listOfSearchFields = '';

    /**
     * Override default constructor to make it possible to instantiate this
     * class for indexed_search
     *
     * @param \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $cObj
     */
    public function __construct(ContentObjectRenderer $cObj = null)
    {
        if (!is_null($cObj)) {
            $this->cObj = $cObj;
        }
    }

    /**
     * Rendering the cObject, SEARCHRESULT
     *
     * @param array $conf Array of TypoScript properties
     * @return string Output
     */
    public function render($conf = [])
    {
        if (\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('sword') && \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('scols')) {
            $this->register_and_explode_search_string(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('sword'));
            $this->register_tables_and_columns(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('scols'), $conf['allowedCols']);
            // Depth
            $depth = 100;
            // The startId is found
            $theStartId = 0;
            if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('stype'))) {
                $temp_theStartId = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('stype');
                $rootLine = $GLOBALS['TSFE']->sys_page->getRootLine($temp_theStartId);
                // The page MUST have a rootline with the Level0-page of the current site inside!!
                foreach ($rootLine as $val) {
                    if ($val['uid'] == $GLOBALS['TSFE']->tmpl->rootLine[0]['uid']) {
                        $theStartId = $temp_theStartId;
                    }
                }
            } elseif (\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('stype')) {
                if (substr(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('stype'), 0, 1) == 'L') {
                    $pointer = (int)substr(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('stype'), 1);
                    $theRootLine = $GLOBALS['TSFE']->tmpl->rootLine;
                    // location Data:
                    $locDat_arr = explode(':', \TYPO3\CMS\Core\Utility\GeneralUtility::_POST('locationData'));
                    $pId = (int)$locDat_arr[0];
                    if ($pId) {
                        $altRootLine = $GLOBALS['TSFE']->sys_page->getRootLine($pId);
                        ksort($altRootLine);
                        if (!empty($altRootLine)) {
                            // Check if the rootline has the real Level0 in it!!
                            $hitRoot = 0;
                            $theNewRoot = [];
                            foreach ($altRootLine as $val) {
                                if ($hitRoot || $val['uid'] == $GLOBALS['TSFE']->tmpl->rootLine[0]['uid']) {
                                    $hitRoot = 1;
                                    $theNewRoot[] = $val;
                                }
                            }
                            if ($hitRoot) {
                                // Override the real rootline if any thing
                                $theRootLine = $theNewRoot;
                            }
                        }
                    }
                    $key = $this->cObj->getKey($pointer, $theRootLine);
                    $theStartId = $theRootLine[$key]['uid'];
                }
            }
            if (!$theStartId) {
                // If not set, we use current page
                $theStartId = $GLOBALS['TSFE']->id;
            }
            // Generate page-tree
            $this->pageIdList .= $this->cObj->getTreeList(-1 * $theStartId, $depth);
            $endClause = 'pages.uid IN (' . $this->pageIdList . ')
				AND pages.doktype in (' . $GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'] . ($conf['addExtUrlsAndShortCuts'] ? ',3,4' : '') . ')
				AND pages.no_search=0' . $this->cObj->enableFields($this->fTable) . $this->cObj->enableFields('pages');
            if ($conf['languageField.'][$this->fTable]) {
                // (using sys_language_uid which is the ACTUAL language of the page.
                // sys_language_content is only for selecting DISPLAY content!)
                $endClause .= ' AND ' . $this->fTable . '.' . $conf['languageField.'][$this->fTable] . ' = ' . (int)$GLOBALS['TSFE']->sys_language_uid;
            }
            // Build query
            $this->build_search_query($endClause);
            // Count...
            if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('scount'))) {
                $this->res_count = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('scount');
            } else {
                $this->count_query();
            }
            // Range
            $spointer = (int)\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('spointer');
            $range = isset($conf['range.']) ? $this->cObj->stdWrap($conf['range'], $conf['range.']) : $conf['range'];
            if ($range) {
                $theRange = (int)$range;
            } else {
                $theRange = 20;
            }
            // Order By:
            $noOrderBy = isset($conf['noOrderBy.']) ? $this->cObj->stdWrap($conf['noOrderBy'], $conf['noOrderBy.']) : $conf['noOrderBy'];
            if (!$noOrderBy) {
                $this->queryParts['ORDERBY'] = 'pages.lastUpdated, pages.tstamp';
            }
            $this->queryParts['LIMIT'] = $spointer . ',' . $theRange;
            // Search...
            $this->execute_query();
            if ($GLOBALS['TYPO3_DB']->sql_num_rows($this->result)) {
                $GLOBALS['TSFE']->register['SWORD_PARAMS'] = $this->get_searchwords();
                $total = $this->res_count;
                $rangeLow = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($spointer + 1, 1, $total);
                $rangeHigh = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($spointer + $theRange, 1, $total);
                // prev/next url:
                $target = isset($conf['target.']) ? $this->cObj->stdWrap($conf['target'], $conf['target.']) : $conf['target'];
                $LD = $GLOBALS['TSFE']->tmpl->linkData($GLOBALS['TSFE']->page, $target, 1, '', '', $this->cObj->getClosestMPvalueForPage($GLOBALS['TSFE']->page['uid']));
                $targetPart = $LD['target'] ? ' target="' . htmlspecialchars($LD['target']) . '"' : '';
                $urlParams = $this->cObj->URLqMark($LD['totalURL'], '&sword=' . rawurlencode(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('sword')) . '&scols=' . rawurlencode(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('scols')) . '&stype=' . rawurlencode(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('stype')) . '&scount=' . $total);
                // substitution:
                $result = str_replace(
                    [
                        '###RANGELOW###',
                        '###RANGEHIGH###',
                        '###TOTAL###'
                    ],
                    [
                        $rangeLow,
                        $rangeHigh,
                        $total
                    ],
                    $this->cObj->cObjGetSingle($conf['layout'], $conf['layout.'], 'layout')
                );
                if ($rangeHigh < $total) {
                    $next = $this->cObj->cObjGetSingle($conf['next'], $conf['next.'], 'next');
                    $next = '<a href="' . htmlspecialchars(($urlParams . '&spointer=' . ($spointer + $theRange))) . '"' . $targetPart . $GLOBALS['TSFE']->ATagParams . '>' . $next . '</a>';
                } else {
                    $next = '';
                }
                $result = str_replace('###NEXT###', $next, $result);
                if ($rangeLow > 1) {
                    $prev = $this->cObj->cObjGetSingle($conf['prev'], $conf['prev.'], 'prev');
                    $prev = '<a href="' . htmlspecialchars(($urlParams . '&spointer=' . ($spointer - $theRange))) . '"' . $targetPart . $GLOBALS['TSFE']->ATagParams . '>' . $prev . '</a>';
                } else {
                    $prev = '';
                }
                $result = str_replace('###PREV###', $prev, $result);
                // Searching result
                $theValue = $this->cObj->cObjGetSingle($conf['resultObj'], $conf['resultObj.'], 'resultObj');
                /** @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $cObj */
                $cObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
                $cObj->setParent($this->cObj->data, $this->cObj->currentRecord);
                $renderCode = '';
                while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($this->result)) {
                    // versionOL() here? This is search result displays, is that possible to preview anyway?
                    // Or are records selected here already future versions?
                    $cObj->start($row);
                    $renderCode .= $cObj->cObjGetSingle($conf['renderObj'], $conf['renderObj.'], 'renderObj');
                }
                $renderWrap = isset($conf['renderWrap.']) ? $this->cObj->stdWrap($conf['renderWrap'], $conf['renderWrap.']) : $conf['renderWrap'];
                $theValue .= $this->cObj->wrap($renderCode, $renderWrap);
                $theValue = str_replace('###RESULT###', $theValue, $result);
            } else {
                $theValue = $this->cObj->cObjGetSingle($conf['noResultObj'], $conf['noResultObj.'], 'noResultObj');
            }
            $GLOBALS['TT']->setTSlogMessage('Search in fields:   ' . $this->listOfSearchFields);
            // Wrapping
            $content = $theValue;
            $wrap = isset($conf['wrap.']) ? $this->cObj->stdWrap($conf['wrap'], $conf['wrap.']) : $conf['wrap'];
            if ($wrap) {
                $content = $this->cObj->wrap($content, $wrap);
            }
            if (isset($conf['stdWrap.'])) {
                $content = $this->cObj->stdWrap($content, $conf['stdWrap.']);
            }
            // Returning, do not cache the result of the search
            $GLOBALS['TSFE']->set_no_cache('Search result page');
            return $content;
        }
        return '';
    }

    /**
     * Creates the $this->tables-array.
     * The 'pages'-table is ALWAYS included as the search is page-based. Apart from this there may be one and only one table, joined with the pages-table. This table is the first table mentioned in the requested-list. If any more tables are set here, they are ignored.
     *
     * @param string $requestedCols is a list (-) of columns that we want to search. This could be input from the search-form (see TypoScript documentation)
     * @param string $allowedCols $allowedCols: is the list of columns, that MAY be searched. All allowed cols are set as result-fields. All requested cols MUST be in the allowed-fields list.
     * @return void
     */
    public function register_tables_and_columns($requestedCols, $allowedCols)
    {
        $rCols = $this->explodeCols($requestedCols);
        $aCols = $this->explodeCols($allowedCols);
        foreach ($rCols as $k => $v) {
            $rCols[$k] = trim($v);
            if (in_array($rCols[$k], $aCols)) {
                $parts = explode('.', $rCols[$k]);
                $this->tables[$parts[0]]['searchfields'][] = $parts[1];
            }
        }
        $this->tables['pages']['primary_key'] = 'uid';
        $this->tables['pages']['resultfields'][] = 'uid';
        unset($this->tables['pages']['fkey']);
        foreach ($aCols as $k => $v) {
            $aCols[$k] = trim($v);
            $parts = explode('.', $aCols[$k]);
            $this->tables[$parts[0]]['resultfields'][] = $parts[1] . ' AS ' . str_replace('.', '_', $aCols[$k]);
            $this->tables[$parts[0]]['fkey'] = 'pid';
        }
        $this->fTable = '';
        foreach ($this->tables as $t => $v) {
            if ($t != 'pages') {
                if (!$this->fTable) {
                    $this->fTable = $t;
                } else {
                    unset($this->tables[$t]);
                }
            }
        }
    }

    /**
     * Function that can convert the syntax for entering which tables/fields the search should be conducted in.
     *
     * @param string $in This is the code-line defining the tables/fields to search. Syntax: '[table1].[field1]-[field2]-[field3] : [table2].[field1]-[field2]'
     * @return array An array where the values is "[table].[field]" strings to search
     * @see register_tables_and_columns()
     */
    public function explodeCols($in)
    {
        $theArray = explode(':', $in);
        $out = [];
        foreach ($theArray as $val) {
            $val = trim($val);
            $parts = explode('.', $val);
            if ($parts[0] && $parts[1]) {
                $subparts = explode('-', $parts[1]);
                foreach ($subparts as $piece) {
                    $piece = trim($piece);
                    if ($piece) {
                        $out[] = $parts[0] . '.' . $piece;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Takes a search-string (WITHOUT SLASHES or else it'll be a little sppooky , NOW REMEMBER to unslash!!)
     * Sets up $this->sword_array op with operators.
     * This function uses $this->operator_translate_table as well as $this->default_operator
     *
     * @param string $sword The input search-word string.
     * @return void
     */
    public function register_and_explode_search_string($sword)
    {
        $sword = trim($sword);
        if ($sword) {
            $components = $this->split($sword);
            // the searchword is stored here during the loop
            $s_sword = '';
            if (is_array($components)) {
                $i = 0;
                $lastoper = '';
                foreach ($components as $key => $val) {
                    $operator = $this->get_operator($val);
                    if ($operator) {
                        $lastoper = $operator;
                    } elseif (strlen($val) > 1) {
                        // A searchword MUST be at least two characters long!
                        $this->sword_array[$i]['sword'] = $val;
                        $this->sword_array[$i]['oper'] = $lastoper ?: $this->default_operator;
                        $lastoper = '';
                        $i++;
                    }
                }
            }
        }
    }

    /**
     * Used to split a search-word line up into elements to search for. This function will detect boolean words like AND and OR, + and -, and even find sentences encapsulated in ""
     * This function could be re-written to be more clean and effective - yet it's not that important.
     *
     * @param string $origSword The raw sword string from outside
     * @param string $specchars Special chars which are used as operators (+- is default)
     * @param string $delchars Special chars which are deleted if the append the searchword (+-., is default)
     * @return mixed Returns an ARRAY if there were search words, otherwise the return value may be unset.
     */
    public function split($origSword, $specchars = '+-', $delchars = '+.,-')
    {
        $sword = $origSword;
        $specs = '[' . preg_quote($specchars, '/') . ']';
        // As long as $sword is TRUE (that means $sword MUST be reduced little by little until its empty inside the loop!)
        while ($sword) {
            // There was a double-quote and we will then look for the ending quote.
            if (preg_match('/^"/', $sword)) {
                // Removes first double-quote
                $sword = preg_replace('/^"/', '', $sword);
                // Removes everything till next double-quote
                preg_match('/^[^"]*/', $sword, $reg);
                // reg[0] is the value, should not be trimmed
                $value[] = $reg[0];
                $sword = preg_replace('/^' . preg_quote($reg[0], '/') . '/', '', $sword);
                // Removes last double-quote
                $sword = trim(preg_replace('/^"/', '', $sword));
            } elseif (preg_match('/^' . $specs . '/', $sword, $reg)) {
                $value[] = $reg[0];
                // Removes = sign
                $sword = trim(preg_replace('/^' . $specs . '/', '', $sword));
            } elseif (preg_match('/[\\+\\-]/', $sword)) {
                // Check if $sword contains + or -
                // + and - shall only be interpreted as $specchars when there's whitespace before it
                // otherwise it's included in the searchword (e.g. "know-how")
                // explode $sword to single words
                $a_sword = explode(' ', $sword);
                // get first word
                $word = array_shift($a_sword);
                // Delete $delchars at end of string
                $word = rtrim($word, $delchars);
                // add searchword to values
                $value[] = $word;
                // re-build $sword
                $sword = implode(' ', $a_sword);
            } else {
                // There are no double-quotes around the value. Looking for next (space) or special char.
                preg_match('/^[^ ' . preg_quote($specchars, '/') . ']*/', $sword, $reg);
                // Delete $delchars at end of string
                $word = rtrim(trim($reg[0]), $delchars);
                $value[] = $word;
                $sword = trim(preg_replace('/^' . preg_quote($reg[0], '/') . '/', '', $sword));
            }
        }
        return $value;
    }

    /**
     * This creates the search-query.
     * In TypoScript this is used for searching only records not hidden, start/endtimed and fe_grouped! (enable-fields, see tt_content)
     * Sets $this->queryParts
     *
     * @param string $endClause Some extra conditions that the search must match.
     * @return bool Returns TRUE no matter what - sweet isn't it!
     * @access private
     * @see \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::SEARCHRESULT()
     */
    public function build_search_query($endClause)
    {
        if (is_array($this->tables)) {
            $tables = $this->tables;
            $primary_table = '';
            // Primary key table is found.
            foreach ($tables as $key => $val) {
                if ($tables[$key]['primary_key']) {
                    $primary_table = $key;
                }
            }
            if ($primary_table) {
                // Initialize query parts:
                $this->queryParts = [
                    'SELECT' => '',
                    'FROM' => '',
                    'WHERE' => '',
                    'GROUPBY' => '',
                    'ORDERBY' => '',
                    'LIMIT' => ''
                ];
                // Find tables / field names to select:
                $fieldArray = [];
                $tableArray = [];
                foreach ($tables as $key => $val) {
                    $tableArray[] = $key;
                    $resultfields = $tables[$key]['resultfields'];
                    if (is_array($resultfields)) {
                        foreach ($resultfields as $key2 => $val2) {
                            $fieldArray[] = $key . '.' . $val2;
                        }
                    }
                }
                $this->queryParts['SELECT'] = implode(',', $fieldArray);
                $this->queryParts['FROM'] = implode(',', $tableArray);
                // Set join WHERE parts:
                $whereArray = [];
                $primary_table_and_key = $primary_table . '.' . $tables[$primary_table]['primary_key'];
                $primKeys = [];
                foreach ($tables as $key => $val) {
                    $fkey = $tables[$key]['fkey'];
                    if ($fkey) {
                        $primKeys[] = $key . '.' . $fkey . '=' . $primary_table_and_key;
                    }
                }
                if (!empty($primKeys)) {
                    $whereArray[] = '(' . implode(' OR ', $primKeys) . ')';
                }
                // Additional where clause:
                if (trim($endClause)) {
                    $whereArray[] = trim($endClause);
                }
                // Add search word where clause:
                $query_part = $this->build_search_query_for_searchwords();
                if (!$query_part) {
                    $query_part = '(0!=0)';
                }
                $whereArray[] = '(' . $query_part . ')';
                // Implode where clauses:
                $this->queryParts['WHERE'] = implode(' AND ', $whereArray);
                // Group by settings:
                if ($this->group_by) {
                    if ($this->group_by == 'PRIMARY_KEY') {
                        $this->queryParts['GROUPBY'] = $primary_table_and_key;
                    } else {
                        $this->queryParts['GROUPBY'] = $this->group_by;
                    }
                }
            }
        }
    }

    /**
     * Creates the part of the SQL-sentence, that searches for the search-words ($this->sword_array)
     *
     * @return string Part of where class limiting result to the those having the search word.
     * @access private
     */
    public function build_search_query_for_searchwords()
    {
        if (is_array($this->sword_array)) {
            $main_query_part = [];
            foreach ($this->sword_array as $key => $val) {
                $s_sword = $this->sword_array[$key]['sword'];
                // Get subQueryPart
                $sub_query_part = [];
                $this->listOfSearchFields = '';
                foreach ($this->tables as $key3 => $val3) {
                    $searchfields = $this->tables[$key3]['searchfields'];
                    if (is_array($searchfields)) {
                        foreach ($searchfields as $key2 => $val2) {
                            $this->listOfSearchFields .= $key3 . '.' . $val2 . ',';
                            $sub_query_part[] = $key3 . '.' . $val2 . ' LIKE \'%' . $GLOBALS['TYPO3_DB']->quoteStr($s_sword, $key3) . '%\'';
                        }
                    }
                }
                if (!empty($sub_query_part)) {
                    $main_query_part[] = $this->sword_array[$key]['oper'];
                    $main_query_part[] = '(' . implode(' OR ', $sub_query_part) . ')';
                }
            }
            if (!empty($main_query_part)) {
                // Remove first part anyways.
                unset($main_query_part[0]);
                return implode(' ', $main_query_part);
            }
        }
    }

    /**
     * This returns an SQL search-operator (eg. AND, OR, NOT) translated from the current localized set of operators (eg. in danish OG, ELLER, IKKE).
     *
     * @param string $operator The possible operator to find in the internal operator array.
     * @return string If found, the SQL operator for the localized input operator.
     * @access private
     */
    public function get_operator($operator)
    {
        $operator = trim($operator);
        $op_array = $this->operator_translate_table;
        if ($this->operator_translate_table_caseinsensitive) {
            // case-conversion is charset insensitive, but it doesn't spoil
            // anything if input string AND operator table is already converted
            $operator = strtolower($operator);
        }
        foreach ($op_array as $key => $val) {
            $item = $op_array[$key][0];
            if ($this->operator_translate_table_caseinsensitive) {
                // See note above.
                $item = strtolower($item);
            }
            if ($operator == $item) {
                return $op_array[$key][1];
            }
        }
    }

    /**
     * Counts the results and sets the result in $this->res_count
     *
     * @return bool TRUE, if $this->query was found
     */
    public function count_query()
    {
        if (is_array($this->queryParts)) {
            $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($this->queryParts['SELECT'], $this->queryParts['FROM'], $this->queryParts['WHERE'], $this->queryParts['GROUPBY']);
            $this->res_count = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
            return true;
        }
    }

    /**
     * Executes the search, sets result pointer in $this->result
     *
     * @return bool TRUE, if $this->query was set and query performed
     */
    public function execute_query()
    {
        if (is_array($this->queryParts)) {
            $this->result = $GLOBALS['TYPO3_DB']->exec_SELECT_queryArray($this->queryParts);
            return true;
        }
    }

    /**
     * Returns URL-parameters with the current search words.
     * Used when linking to result pages so that search words can be highlighted.
     *
     * @return string URL-parameters with the searchwords
     */
    public function get_searchwords()
    {
        $SWORD_PARAMS = '';
        if (is_array($this->sword_array)) {
            foreach ($this->sword_array as $key => $val) {
                $SWORD_PARAMS .= '&sword_list[]=' . rawurlencode($val['sword']);
            }
        }
        return $SWORD_PARAMS;
    }

    /**
     * Returns an array with the search words in
     *
     * @return array IF the internal sword_array contained search words it will return these, otherwise "void
     */
    public function get_searchwordsArray()
    {
        if (is_array($this->sword_array)) {
            foreach ($this->sword_array as $key => $val) {
                $swords[] = $val['sword'];
            }
        }
        return $swords;
    }
}
