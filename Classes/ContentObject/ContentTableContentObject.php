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

/**
 * Contains CTABLE content object.
 */
class ContentTableContentObject extends \TYPO3\CMS\Frontend\ContentObject\AbstractContentObject
{
    /**
     * Rendering the cObject, CTABLE
     *
     * @param array $conf Array of TypoScript properties
     * @return string Output
     */
    public function render($conf = [])
    {
        $controlTable = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(TableRenderer::class);
        $tableParams = isset($conf['tableParams.']) ? $this->cObj->stdWrap($conf['tableParams'], $conf['tableParams.']) : $conf['tableParams'];
        if ($tableParams) {
            $controlTable->tableParams = $tableParams;
        }
        // loads the pagecontent
        $conf['cWidth'] = isset($conf['cWidth.']) ? $this->cObj->stdWrap($conf['cWidth'], $conf['cWidth.']) : $conf['cWidth'];
        $controlTable->contentW = $conf['cWidth'];
        // loads the menues if any
        if (is_array($conf['c.'])) {
            $controlTable->content = $this->cObj->cObjGet($conf['c.'], 'c.');
            $contentTDParams = isset($conf['c.']['TDParams.']) ? $this->cObj->stdWrap($conf['c.']['TDParams'], $conf['c.']['TDParams.']) : $conf['c.']['TDParams'];
            $controlTable->contentTDparams = isset($contentTDParams) ? $contentTDParams : 'valign="top"';
        }
        if (is_array($conf['lm.'])) {
            $controlTable->lm = $this->cObj->cObjGet($conf['lm.'], 'lm.');
            $lmTDParams = isset($conf['lm.']['TDParams.']) ? $this->cObj->stdWrap($conf['lm.']['TDParams'], $conf['lm.']['TDParams.']) : $conf['lm.']['TDParams'];
            $controlTable->lmTDparams = isset($lmTDParams) ? $lmTDParams : 'valign="top"';
        }
        if (is_array($conf['tm.'])) {
            $controlTable->tm = $this->cObj->cObjGet($conf['tm.'], 'tm.');
            $tmTDParams = isset($conf['tm.']['TDParams.']) ? $this->cObj->stdWrap($conf['tm.']['TDParams'], $conf['tm.']['TDParams.']) : $conf['tm.']['TDParams'];
            $controlTable->tmTDparams = isset($tmTDParams) ? $tmTDParams : 'valign="top"';
        }
        if (is_array($conf['rm.'])) {
            $controlTable->rm = $this->cObj->cObjGet($conf['rm.'], 'rm.');
            $rmTDParams = isset($conf['rm.']['TDParams.']) ? $this->cObj->stdWrap($conf['rm.']['TDParams'], $conf['rm.']['TDParams.']) : $conf['rm.']['TDParams'];
            $controlTable->rmTDparams = isset($rmTDParams) ? $rmTDParams : 'valign="top"';
        }
        if (is_array($conf['bm.'])) {
            $controlTable->bm = $this->cObj->cObjGet($conf['bm.'], 'bm.');
            $bmTDParams = isset($conf['bm.']['TDParams.']) ? $this->cObj->stdWrap($conf['bm.']['TDParams'], $conf['bm.']['TDParams.']) : $conf['bm.']['TDParams'];
            $controlTable->bmTDparams = isset($bmTDParams) ? $bmTDParams : 'valign="top"';
        }
        $conf['offset'] = isset($conf['offset.']) ? $this->cObj->stdWrap($conf['offset'], $conf['offset.']) : $conf['offset'];
        $conf['cMargins'] = isset($conf['cMargins.']) ? $this->cObj->stdWrap($conf['cMargins'], $conf['cMargins.']) : $conf['cMargins'];
        $theValue = $controlTable->start($conf['offset'], $conf['cMargins']);
        if (isset($conf['stdWrap.'])) {
            $theValue = $this->cObj->stdWrap($theValue, $conf['stdWrap.']);
        }
        return $theValue;
    }
}
