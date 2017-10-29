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
 * Rendering of tables for content positioning
 *
 * @see ContentObjectRenderer::CTABLE()
 */
class TableRenderer
{
    /**
     * offset, x
     *
     * @var int
     */
    public $offX = 0;

    /**
     * offset, y
     *
     * @var int
     */
    public $offY = 0;

    /**
     * top menu
     *
     * @var string
     */
    public $tm = '';

    /**
     * left menu
     *
     * @var string
     */
    public $lm = '';

    /**
     * right menu
     *
     * @var string
     */
    public $rm = '';

    /**
     * bottom menu
     *
     * @var string
     */
    public $bm = '';

    /**
     * content
     *
     * @var string
     */
    public $content = '';

    /**
     * top menu TDparams
     *
     * @var string
     */
    public $tmTDparams = 'valign="top"';

    /**
     * left menu TDparams
     *
     * @var string
     */
    public $lmTDparams = 'valign="top"';

    /**
     * right menu TDparams
     *
     * @var string
     */
    public $rmTDparams = 'valign="top"';

    /**
     * bottom menu TDparams
     *
     * @var string
     */
    public $bmTDparams = 'valign="top"';

    /**
     * content TDparams
     *
     * @var string
     */
    public $contentTDparams = 'valign="top"';

    /**
     * content margin, left
     *
     * @var int
     */
    public $cMl = 1;

    /**
     * content margin, right
     *
     * @var int
     */
    public $cMr = 1;

    /**
     * content margin, top
     *
     * @var int
     */
    public $cMt = 0;

    /**
     * content margin, bottom
     *
     * @var int
     */
    public $cMb = 1;

    /**
     * Places a little gif-spacer in the bottom of the content frame
     *
     * @var int
     */
    public $contentW = 0;

    /**
     * @var string
     */
    public $tableParams = 'border="0" cellspacing="0" cellpadding="0"';

    /**
     * Wrapping internal vars ->tm, ->lm, ->rm, ->bm and ->content in a table where each content part is stored in a cell.
     * The two arguments to this function defines some offsets and margins to use in the arrangement of the content in the table.
     *
     * @param string $offset List of offset parameters; x,y
     * @param string $cMargins List of margin parameters; left, top, right, bottom
     * @return string The content strings wrapped in a <table> as the parameters defined
     * @see \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::CTABLE()
     */
    public function start($offset, $cMargins)
    {
        $offArr = \TYPO3\CMS\Core\Utility\GeneralUtility::intExplode(',', $offset);
        $cMargArr = \TYPO3\CMS\Core\Utility\GeneralUtility::intExplode(',', $cMargins);
        $cols = 0;
        $rows = 0;
        if ($this->lm) {
            $cols++;
        }
        if ($this->rm) {
            $cols++;
        }
        if ($cMargArr[0]) {
            $cols++;
        }
        if ($cMargArr[2]) {
            $cols++;
        }
        if ($cMargArr[1] || $cMargArr[3] || $this->tm || $this->bm || $this->content || $this->contentW) {
            $cols++;
        }
        if ($cMargArr[1]) {
            $rows++;
        }
        if ($cMargArr[3]) {
            $rows++;
        }
        if ($this->tm) {
            $rows++;
        }
        if ($this->bm) {
            $rows++;
        }
        if ($this->content) {
            $rows++;
        }
        if ($this->contentW) {
            $rows++;
        }
        if (!$rows && $cols) {
            // If there are no rows in the middle but still som columns...
            $rows = 1;
        }
        if ($rows && $cols) {
            $res = LF . '<table ' . $this->tableParams . '>';
            // Top offset:
            if ($offArr[1]) {
                $xoff = $offArr[0] ? 1 : 0;
                if ($cols + $xoff > 1) {
                    $colspan = ' colspan="' . ($cols + $xoff) . '"';
                }
                $res .= '<tr><td' . $colspan . '><span style="width: 1px; height: ' . $offArr[1] . 'px;"></span></td></tr>';
            }
            // The rows:
            if ($rows > 1) {
                $rowspan = ' rowspan="' . $rows . '"';
            }
            $res .= '<tr>';
            if ($offArr[0]) {
                $res .= '<td' . $rowspan . '><span style="width: ' . $offArr[0] . 'px; height: 1px;"></span></td>';
            }
            if ($this->lm) {
                $res .= '<td' . $rowspan . ' ' . $this->lmTDparams . '>' . $this->lm . '</td>';
            }
            if ($cMargArr[0]) {
                $res .= '<td' . $rowspan . '><span style="width: ' . $cMargArr[0] . 'px; height: 1px;"></span></td>';
            }
            // Content...
            $middle = [];
            if ($this->tm) {
                $middle[] = '<td ' . $this->tmTDparams . '>' . $this->tm . '</td>';
            }
            if ($cMargArr[1]) {
                $middle[] = '<td><span style="width: 1px; height: ' . $cMargArr[1] . 'px;"></span></td>';
            }
            if ($this->content) {
                $middle[] = '<td ' . $this->contentTDparams . '>' . $this->content . '</td>';
            }
            if ($cMargArr[3]) {
                $middle[] = '<td><span style="width: 1px; height: ' . $cMargArr[3] . 'px;"></span></td>';
            }
            if ($this->bm) {
                $middle[] = '<td ' . $this->bmTDparams . '>' . $this->bm . '</td>';
            }
            if ($this->contentW) {
                $middle[] = '<td><span style="width: ' . $this->contentW . 'px; height: 1px;"></span></td>';
            }
            if (isset($middle[0])) {
                $res .= $middle[0];
            }
            // Left of content
            if ($cMargArr[2]) {
                $res .= '<td' . $rowspan . '><span style="width: ' . $cMargArr[2] . 'px; height: 1px;"></span></td>';
            }
            if ($this->rm) {
                $res .= '<td' . $rowspan . ' ' . $this->rmTDparams . '>' . $this->rm . '</td>';
            }
            $res .= '</tr>';
            // More than the two rows
            $mCount = count($middle);
            for ($a = 1; $a < $mCount; $a++) {
                $res .= '<tr>' . $middle[$a] . '</tr>';
            }
            $res .= '</table>';
            return $res;
        }
    }
}
