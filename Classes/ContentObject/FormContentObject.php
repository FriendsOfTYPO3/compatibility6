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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Contains FORM class object.
 */
class FormContentObject extends \TYPO3\CMS\Frontend\ContentObject\AbstractContentObject
{
    /**
     * Rendering the cObject, FORM
     *
     * Note on $formData:
     * In the optional $formData array each entry represents a line in the ordinary setup.
     * In those entries each entry (0,1,2...) represents a space normally divided by the '|' line.
     *
     * $formData [] = array('Name:', 'name=input, 25 ', 'Default value....');
     * $formData [] = array('Email:', 'email=input, 25 ', 'Default value for email....');
     *
     * - corresponds to the $conf['data'] value being :
     * Name:|name=input, 25 |Default value....||Email:|email=input, 25 |Default value for email....
     *
     * If $formData is an array the value of $conf['data'] is ignored.
     *
     * @param array $conf Array of TypoScript properties
     * @param array $formData Alternative formdata overriding whatever comes from TypoScript
     * @return string Output
     */
    public function render($conf = [], $formData = '')
    {
        $content = '';
        if (is_array($formData)) {
            $dataArray = $formData;
        } else {
            $data = isset($conf['data.']) ? $this->cObj->stdWrap($conf['data'], $conf['data.']) : $conf['data'];
            // Clearing dataArr
            $dataArray = [];
            // Getting the original config
            if (trim($data)) {
                $data = str_replace(LF, '||', $data);
                $dataArray = explode('||', $data);
            }
            // Adding the new dataArray config form:
            if (is_array($conf['dataArray.'])) {
                // dataArray is supplied
                $sortedKeyArray = \TYPO3\CMS\Core\TypoScript\TemplateService::sortedKeyList($conf['dataArray.'], true);
                foreach ($sortedKeyArray as $theKey) {
                    $singleKeyArray = $conf['dataArray.'][$theKey . '.'];
                    if (is_array($singleKeyArray)) {
                        $temp = [];
                        $label = isset($singleKeyArray['label.']) ? $this->cObj->stdWrap($singleKeyArray['label'], $singleKeyArray['label.']) : $singleKeyArray['label'];
                        list($temp[0]) = explode('|', $label);
                        $type = isset($singleKeyArray['type.']) ? $this->cObj->stdWrap($singleKeyArray['type'], $singleKeyArray['type.']) : $singleKeyArray['type'];
                        list($temp[1]) = explode('|', $type);
                        $required = isset($singleKeyArray['required.']) ? $this->cObj->stdWrap($singleKeyArray['required'], $singleKeyArray['required.']) : $singleKeyArray['required'];
                        if ($required) {
                            $temp[1] = '*' . $temp[1];
                        }
                        $singleValue = isset($singleKeyArray['value.']) ? $this->cObj->stdWrap($singleKeyArray['value'], $singleKeyArray['value.']) : $singleKeyArray['value'];
                        list($temp[2]) = explode('|', $singleValue);
                        // If value array is set, then implode those values.
                        if (is_array($singleKeyArray['valueArray.'])) {
                            $temp_accumulated = [];
                            foreach ($singleKeyArray['valueArray.'] as $singleKey => $singleKey_valueArray) {
                                if (is_array($singleKey_valueArray) && (int)$singleKey . '.' === (string)$singleKey) {
                                    $temp_valueArray = [];
                                    $valueArrayLabel = isset($singleKey_valueArray['label.']) ? $this->cObj->stdWrap($singleKey_valueArray['label'], $singleKey_valueArray['label.']) : $singleKey_valueArray['label'];
                                    list($temp_valueArray[0]) = explode('=', $valueArrayLabel);
                                    $selected = isset($singleKey_valueArray['selected.']) ? $this->cObj->stdWrap($singleKey_valueArray['selected'], $singleKey_valueArray['selected.']) : $singleKey_valueArray['selected'];
                                    if ($selected) {
                                        $temp_valueArray[0] = '*' . $temp_valueArray[0];
                                    }
                                    $singleKeyValue = isset($singleKey_valueArray['value.']) ? $this->cObj->stdWrap($singleKey_valueArray['value'], $singleKey_valueArray['value.']) : $singleKey_valueArray['value'];
                                    list($temp_valueArray[1]) = explode(',', $singleKeyValue);
                                }
                                $temp_accumulated[] = implode('=', $temp_valueArray);
                            }
                            $temp[2] = implode(',', $temp_accumulated);
                        }
                        $specialEval = isset($singleKeyArray['specialEval.']) ? $this->cObj->stdWrap($singleKeyArray['specialEval'], $singleKeyArray['specialEval.']) : $singleKeyArray['specialEval'];
                        list($temp[3]) = explode('|', $specialEval);
                        // Adding the form entry to the dataArray
                        $dataArray[] = implode('|', $temp);
                    }
                }
            }
        }
        $attachmentCounter = '';
        $hiddenfields = '';
        $fieldlist = [];
        $propertyOverride = [];
        $fieldname_hashArray = [];
        $counter = 0;
        $xhtmlStrict = GeneralUtility::inList('xhtml_strict,xhtml_11,xhtml_2', $GLOBALS['TSFE']->xhtmlDoctype);
        // Formname
        $formName = isset($conf['formName.']) ? $this->cObj->stdWrap($conf['formName'], $conf['formName.']) : $conf['formName'];
        $formName = $this->cleanFormName($formName);
        $formName = $GLOBALS['TSFE']->getUniqueId($formName);

        $fieldPrefix = isset($conf['fieldPrefix.']) ? $this->cObj->stdWrap($conf['fieldPrefix'], $conf['fieldPrefix.']) : $conf['fieldPrefix'];
        if (isset($conf['fieldPrefix']) || isset($conf['fieldPrefix.'])) {
            if ($fieldPrefix) {
                $prefix = $this->cleanFormName($fieldPrefix);
            } else {
                $prefix = '';
            }
        } else {
            $prefix = $formName;
        }
        foreach ($dataArray as $dataValue) {
            $counter++;
            $confData = [];
            if (is_array($formData)) {
                $parts = $dataValue;
                // TRUE...
                $dataValue = 1;
            } else {
                $dataValue = trim($dataValue);
                $parts = explode('|', $dataValue);
            }
            if ($dataValue && strcspn($dataValue, '#/')) {
                // label:
                $confData['label'] = GeneralUtility::removeXSS(trim($parts[0]));
                // field:
                $fParts = explode(',', $parts[1]);
                $fParts[0] = trim($fParts[0]);
                if ($fParts[0][0] === '*') {
                    $confData['required'] = 1;
                    $fParts[0] = substr($fParts[0], 1);
                }
                $typeParts = explode('=', $fParts[0]);
                $confData['type'] = trim(strtolower(end($typeParts)));
                if (count($typeParts) === 1) {
                    $confData['fieldname'] = $this->cleanFormName($parts[0]);
                    if (strtolower(preg_replace('/[^[:alnum:]]/', '', $confData['fieldname'])) == 'email') {
                        $confData['fieldname'] = 'email';
                    }
                    // Duplicate fieldnames resolved
                    if (isset($fieldname_hashArray[md5($confData['fieldname'])])) {
                        $confData['fieldname'] .= '_' . $counter;
                    }
                    $fieldname_hashArray[md5($confData['fieldname'])] = $confData['fieldname'];
                    // Attachment names...
                    if ($confData['type'] == 'file') {
                        $confData['fieldname'] = 'attachment' . $attachmentCounter;
                        $attachmentCounter = (int)$attachmentCounter + 1;
                    }
                } else {
                    $confData['fieldname'] = str_replace(' ', '_', trim($typeParts[0]));
                }
                $confData['fieldname'] = htmlspecialchars($confData['fieldname']);
                $fieldCode = '';
                $wrapFieldName = isset($conf['wrapFieldName']) ? $this->cObj->stdWrap($conf['wrapFieldName'], $conf['wrapFieldName.']) : $conf['wrapFieldName'];
                if ($wrapFieldName) {
                    $confData['fieldname'] = $this->cObj->wrap($confData['fieldname'], $wrapFieldName);
                }
                // Set field name as current:
                $this->cObj->setCurrentVal($confData['fieldname']);
                // Additional parameters
                if (trim($confData['type'])) {
                    if (isset($conf['params.'][$confData['type']])) {
                        $addParams = isset($conf['params.'][$confData['type'] . '.']) ? trim($this->cObj->stdWrap($conf['params.'][$confData['type']], $conf['params.'][$confData['type'] . '.'])) : trim($conf['params.'][$confData['type']]);
                    } else {
                        $addParams = isset($conf['params.']) ? trim($this->cObj->stdWrap($conf['params'], $conf['params.'])) : trim($conf['params']);
                    }
                    if ((string)$addParams !== '') {
                        $addParams = ' ' . $addParams;
                    }
                } else {
                    $addParams = '';
                }
                $dontMd5FieldNames = isset($conf['dontMd5FieldNames.']) ? $this->cObj->stdWrap($conf['dontMd5FieldNames'], $conf['dontMd5FieldNames.']) : $conf['dontMd5FieldNames'];
                if ($dontMd5FieldNames) {
                    $fName = $confData['fieldname'];
                } else {
                    $fName = md5($confData['fieldname']);
                }
                // Accessibility: Set id = fieldname attribute:
                $accessibility = isset($conf['accessibility.']) ? $this->cObj->stdWrap($conf['accessibility'], $conf['accessibility.']) : $conf['accessibility'];
                if ($accessibility || $xhtmlStrict) {
                    $elementIdAttribute = ' id="' . $prefix . $fName . '"';
                } else {
                    $elementIdAttribute = '';
                }
                // Create form field based on configuration/type:
                switch ($confData['type']) {
                    case 'textarea':
                        $cols = trim($fParts[1]) ? (int)$fParts[1] : 20;
                        $compensateFieldWidth = isset($conf['compensateFieldWidth.']) ? $this->cObj->stdWrap($conf['compensateFieldWidth'], $conf['compensateFieldWidth.']) : $conf['compensateFieldWidth'];
                        $compWidth = floatval($compensateFieldWidth ? $compensateFieldWidth : $GLOBALS['TSFE']->compensateFieldWidth);
                        $compWidth = $compWidth ? $compWidth : 1;
                        $cols = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($cols * $compWidth, 1, 120);
                        $rows = trim($fParts[2]) ? \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($fParts[2], 1, 30) : 5;
                        $wrap = trim($fParts[3]);
                        $noWrapAttr = isset($conf['noWrapAttr.']) ? $this->cObj->stdWrap($conf['noWrapAttr'], $conf['noWrapAttr.']) : $conf['noWrapAttr'];
                        if ($noWrapAttr || $wrap === 'disabled') {
                            $wrap = '';
                        } else {
                            $wrap = $wrap ? ' wrap="' . $wrap . '"' : ' wrap="virtual"';
                        }
                        $noValueInsert = isset($conf['noValueInsert.']) ? $this->cObj->stdWrap($conf['noValueInsert'], $conf['noValueInsert.']) : $conf['noValueInsert'];
                        $default = $this->getFieldDefaultValue($noValueInsert, $confData['fieldname'], str_replace('\\n', LF, trim($parts[2])));
                        $fieldCode = sprintf('<textarea name="%s"%s cols="%s" rows="%s"%s%s>%s</textarea>', $confData['fieldname'], $elementIdAttribute, $cols, $rows, $wrap, $addParams, htmlspecialchars($default));
                        break;
                    case 'input':

                    case 'password':
                        $size = trim($fParts[1]) ? (int)$fParts[1] : 20;
                        $compensateFieldWidth = isset($conf['compensateFieldWidth.']) ? $this->cObj->stdWrap($conf['compensateFieldWidth'], $conf['compensateFieldWidth.']) : $conf['compensateFieldWidth'];
                        $compWidth = floatval($compensateFieldWidth ? $compensateFieldWidth : $GLOBALS['TSFE']->compensateFieldWidth);
                        $compWidth = $compWidth ? $compWidth : 1;
                        $size = \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($size * $compWidth, 1, 120);
                        $noValueInsert = isset($conf['noValueInsert.']) ? $this->cObj->stdWrap($conf['noValueInsert'], $conf['noValueInsert.']) : $conf['noValueInsert'];
                        $default = $this->getFieldDefaultValue($noValueInsert, $confData['fieldname'], trim($parts[2]));
                        if ($confData['type'] == 'password') {
                            $default = '';
                        }
                        $max = trim($fParts[2]) ? ' maxlength="' . \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($fParts[2], 1, 1000) . '"' : '';
                        $theType = $confData['type'] == 'input' ? 'text' : 'password';
                        $fieldCode = sprintf('<input type="%s" name="%s"%s size="%s"%s value="%s"%s />', $theType, $confData['fieldname'], $elementIdAttribute, $size, $max, htmlspecialchars($default), $addParams);
                        break;
                    case 'file':
                        $size = trim($fParts[1]) ? \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($fParts[1], 1, 60) : 20;
                        $fieldCode = sprintf('<input type="file" name="%s"%s size="%s"%s />', $confData['fieldname'], $elementIdAttribute, $size, $addParams);
                        break;
                    case 'check':
                        // alternative default value:
                        $noValueInsert = isset($conf['noValueInsert.']) ? $this->cObj->stdWrap($conf['noValueInsert'], $conf['noValueInsert.']) : $conf['noValueInsert'];
                        $default = $this->getFieldDefaultValue($noValueInsert, $confData['fieldname'], trim($parts[2]));
                        $checked = $default ? ' checked="checked"' : '';
                        $fieldCode = sprintf('<input type="checkbox" value="%s" name="%s"%s%s%s />', 1, $confData['fieldname'], $elementIdAttribute, $checked, $addParams);
                        break;
                    case 'select':
                        $option = '';
                        $valueParts = explode(',', $parts[2]);
                        // size
                        if (strtolower(trim($fParts[1])) == 'auto') {
                            $fParts[1] = count($valueParts);
                        }
                        // Auto size set here. Max 20
                        $size = trim($fParts[1]) ? \TYPO3\CMS\Core\Utility\MathUtility::forceIntegerInRange($fParts[1], 1, 20) : 1;
                        // multiple
                        $multiple = strtolower(trim($fParts[2])) == 'm' ? ' multiple="multiple"' : '';
                        // Where the items will be
                        $items = [];
                        //RTF
                        $defaults = [];
                        $pCount = count($valueParts);
                        for ($a = 0; $a < $pCount; $a++) {
                            $valueParts[$a] = trim($valueParts[$a]);
                            // Finding default value
                            if ($valueParts[$a][0] === '*') {
                                $sel = 'selected';
                                $valueParts[$a] = substr($valueParts[$a], 1);
                            } else {
                                $sel = '';
                            }
                            // Get value/label
                            $subParts = explode('=', $valueParts[$a]);
                            // Sets the value
                            $subParts[1] = isset($subParts[1]) ? trim($subParts[1]) : trim($subParts[0]);
                            // Adds the value/label pair to the items-array
                            $items[] = $subParts;
                            if ($sel) {
                                $defaults[] = $subParts[1];
                            }
                        }
                        // alternative default value:
                        $noValueInsert = isset($conf['noValueInsert.']) ? $this->cObj->stdWrap($conf['noValueInsert'], $conf['noValueInsert.']) : $conf['noValueInsert'];
                        $default = $this->getFieldDefaultValue($noValueInsert, $confData['fieldname'], $defaults);
                        if (!is_array($default)) {
                            $defaults = [];
                            $defaults[] = $default;
                        } else {
                            $defaults = $default;
                        }
                        // Create the select-box:
                        $iCount = count($items);
                        for ($a = 0; $a < $iCount; $a++) {
                            $option .= '<option value="' . htmlspecialchars($items[$a][1]) . '"' .
                                (in_array($items[$a][1], $defaults) ? ' selected="selected"' : '') . '>' .
                                htmlspecialchars(trim($items[$a][0])) .
                                '</option>';
                        }
                        if ($multiple) {
                            // The fieldname must be prepended '[]' if multiple select. And the reason why it's prepended is, because the required-field list later must also have [] prepended.
                            $confData['fieldname'] .= '[]';
                        }
                        $fieldCode = sprintf('<select name="%s"%s size="%s"%s%s>%s</select>', $confData['fieldname'], $elementIdAttribute, $size, $multiple, $addParams, $option);
                        //RTF
                        break;
                    case 'radio':
                        $option = '';
                        $valueParts = explode(',', $parts[2]);
                        // Where the items will be
                        $items = [];
                        $default = '';
                        $pCount = count($valueParts);
                        for ($a = 0; $a < $pCount; $a++) {
                            $valueParts[$a] = trim($valueParts[$a]);
                            if ($valueParts[$a][0] === '*') {
                                $sel = 'checked';
                                $valueParts[$a] = substr($valueParts[$a], 1);
                            } else {
                                $sel = '';
                            }
                            // Get value/label
                            $subParts = explode('=', $valueParts[$a]);
                            // Sets the value
                            $subParts[1] = isset($subParts[1]) ? trim($subParts[1]) : trim($subParts[0]);
                            // Adds the value/label pair to the items-array
                            $items[] = $subParts;
                            if ($sel) {
                                $default = $subParts[1];
                            }
                        }
                        // alternative default value:
                        $noValueInsert = isset($conf['noValueInsert.']) ? $this->cObj->stdWrap($conf['noValueInsert'], $conf['noValueInsert.']) : $conf['noValueInsert'];
                        $default = $this->getFieldDefaultValue($noValueInsert, $confData['fieldname'], $default);
                        // Create the select-box:
                        $iCount = count($items);
                        for ($a = 0; $a < $iCount; $a++) {
                            $optionParts = '';
                            $radioId = $prefix . $fName . $this->cleanFormName($items[$a][0]);
                            if ($accessibility) {
                                $radioLabelIdAttribute = ' id="' . htmlspecialchars($radioId) . '"';
                            } else {
                                $radioLabelIdAttribute = '';
                            }
                            $optionParts .= '<input type="radio" name="' . $confData['fieldname'] . '"' . $radioLabelIdAttribute . ' value="' . htmlspecialchars($items[$a][1]) . '"' . ((string)$items[$a][1] === (string)$default ? ' checked="checked"' : '') . $addParams . ' />';
                            if ($accessibility) {
                                $label = isset($conf['radioWrap.']) ? $this->cObj->stdWrap(trim($items[$a][0]), $conf['radioWrap.']) : trim($items[$a][0]);
                                $optionParts .= '<label for="' . $radioId . '">' . $label . '</label>';
                            } else {
                                $optionParts .= isset($conf['radioWrap.']) ? $this->cObj->stdWrap(trim($items[$a][0]), $conf['radioWrap.']) : htmlspecialchars(trim($items[$a][0]));
                            }
                            $option .= isset($conf['radioInputWrap.']) ? $this->cObj->stdWrap($optionParts, $conf['radioInputWrap.']) : $optionParts;
                        }
                        if ($accessibility) {
                            $accessibilityWrap = isset($conf['radioWrap.']['accessibilityWrap.']) ? $this->cObj->stdWrap($conf['radioWrap.']['accessibilityWrap'], $conf['radioWrap.']['accessibilityWrap.']) : $conf['radioWrap.']['accessibilityWrap'];
                            if ($accessibilityWrap) {
                                $search = [
                                    '###RADIO_FIELD_ID###',
                                    '###RADIO_GROUP_LABEL###'
                                ];
                                $replace = [
                                    $elementIdAttribute,
                                    $confData['label']
                                ];
                                $accessibilityWrap = str_replace($search, $replace, $accessibilityWrap);
                                $option = $this->cObj->wrap($option, $accessibilityWrap);
                            }
                        }
                        $fieldCode = $option;
                        break;
                    case 'hidden':
                        $value = trim($parts[2]);
                        // If this form includes an auto responder message, include a HMAC checksum field
                        // in order to verify potential abuse of this feature.
                        if ($value !== '') {
                            if (GeneralUtility::inList($confData['fieldname'], 'auto_respond_msg')) {
                                $hmacChecksum = GeneralUtility::hmac($value, 'content_form');
                                $hiddenfields .= sprintf('<input type="hidden" name="auto_respond_checksum" id="%sauto_respond_checksum" value="%s" />', $prefix, $hmacChecksum);
                            }
                            if (GeneralUtility::inList('recipient_copy,recipient', $confData['fieldname']) && $GLOBALS['TYPO3_CONF_VARS']['FE']['secureFormmail']) {
                                break;
                            }
                            if (GeneralUtility::inList('recipient_copy,recipient', $confData['fieldname'])) {
                                $value = \TYPO3\CMS\Compatibility6\Utility\FormUtility::codeString($value);
                            }
                        }
                        $hiddenfields .= sprintf('<input type="hidden" name="%s"%s value="%s" />', $confData['fieldname'], $elementIdAttribute, htmlspecialchars($value));
                        break;
                    case 'property':
                        if (GeneralUtility::inList('type,locationData,goodMess,badMess,emailMess', $confData['fieldname'])) {
                            $value = trim($parts[2]);
                            $propertyOverride[$confData['fieldname']] = $value;
                            $conf[$confData['fieldname']] = $value;
                        }
                        break;
                    case 'submit':
                        $value = trim($parts[2]);
                        if ($conf['image.']) {
                            $this->cObj->data[$this->cObj->currentValKey] = $value;
                            $image = $this->cObj->cObjGetSingle('IMG_RESOURCE', $conf['image.']);
                            $params = $conf['image.']['params'] ? ' ' . $conf['image.']['params'] : '';
                            $params .= $this->cObj->getAltParam($conf['image.'], false);
                            $params .= $addParams;
                        } else {
                            $image = '';
                        }
                        if ($image) {
                            $fieldCode = sprintf('<input type="image" name="%s"%s src="%s"%s />', $confData['fieldname'], $elementIdAttribute, $image, $params);
                        } else {
                            $fieldCode = sprintf('<input type="submit" name="%s"%s value="%s"%s />', $confData['fieldname'], $elementIdAttribute, htmlspecialchars($value, ENT_COMPAT, 'UTF-8', false), $addParams);
                        }
                        break;
                    case 'reset':
                        $value = trim($parts[2]);
                        $fieldCode = sprintf('<input type="reset" name="%s"%s value="%s"%s />', $confData['fieldname'], $elementIdAttribute, htmlspecialchars($value, ENT_COMPAT, 'UTF-8', false), $addParams);
                        break;
                    case 'label':
                        $fieldCode = nl2br(htmlspecialchars(trim($parts[2])));
                        break;
                    default:
                        $confData['type'] = 'comment';
                        $fieldCode = trim($parts[2]) . '&nbsp;';
                }
                if ($fieldCode) {
                    // Checking for special evaluation modes:
                    if (trim($parts[3]) !== '' && GeneralUtility::inList('textarea,input,password', $confData['type'])) {
                        $modeParameters = GeneralUtility::trimExplode(':', $parts[3]);
                    } else {
                        $modeParameters = [];
                    }
                    // Adding evaluation based on settings:
                    switch ((string)$modeParameters[0]) {
                        case 'EREG':
                            $fieldlist[] = '_EREG';
                            $fieldlist[] = $modeParameters[1];
                            $fieldlist[] = $modeParameters[2];
                            $fieldlist[] = $confData['fieldname'];
                            $fieldlist[] = $confData['label'];
                            // Setting this so "required" layout is used.
                            $confData['required'] = 1;
                            break;
                        case 'EMAIL':
                            $fieldlist[] = '_EMAIL';
                            $fieldlist[] = $confData['fieldname'];
                            $fieldlist[] = $confData['label'];
                            // Setting this so "required" layout is used.
                            $confData['required'] = 1;
                            break;
                        default:
                            if ($confData['required']) {
                                $fieldlist[] = $confData['fieldname'];
                                $fieldlist[] = $confData['label'];
                            }
                    }
                    // Field:
                    $fieldLabel = $confData['label'];
                    if ($accessibility && trim($fieldLabel) && !preg_match('/^(label|hidden|comment)$/', $confData['type'])) {
                        $fieldLabel = '<label for="' . $prefix . $fName . '">' . $fieldLabel . '</label>';
                    }
                    // Getting template code:
                    if (isset($conf['fieldWrap.'])) {
                        $fieldCode = $this->cObj->stdWrap($fieldCode, $conf['fieldWrap.']);
                    }
                    $labelCode = isset($conf['labelWrap.']) ? $this->cObj->stdWrap($fieldLabel, $conf['labelWrap.']) : $fieldLabel;
                    $commentCode = isset($conf['commentWrap.']) ? $this->cObj->stdWrap($confData['label'], $conf['commentWrap.']) : $confData['label'];
                    $result = $conf['layout'];
                    $req = isset($conf['REQ.']) ? $this->cObj->stdWrap($conf['REQ'], $conf['REQ.']) : $conf['REQ'];
                    if ($req && $confData['required']) {
                        if (isset($conf['REQ.']['fieldWrap.'])) {
                            $fieldCode = $this->cObj->stdWrap($fieldCode, $conf['REQ.']['fieldWrap.']);
                        }
                        if (isset($conf['REQ.']['labelWrap.'])) {
                            $labelCode = $this->cObj->stdWrap($fieldLabel, $conf['REQ.']['labelWrap.']);
                        }
                        $reqLayout = isset($conf['REQ.']['layout.']) ? $this->cObj->stdWrap($conf['REQ.']['layout'], $conf['REQ.']['layout.']) : $conf['REQ.']['layout'];
                        if ($reqLayout) {
                            $result = $reqLayout;
                        }
                    }
                    if ($confData['type'] == 'comment') {
                        $commentLayout = isset($conf['COMMENT.']['layout.']) ? $this->cObj->stdWrap($conf['COMMENT.']['layout'], $conf['COMMENT.']['layout.']) : $conf['COMMENT.']['layout'];
                        if ($commentLayout) {
                            $result = $commentLayout;
                        }
                    }
                    if ($confData['type'] == 'check') {
                        $checkLayout = isset($conf['CHECK.']['layout.']) ? $this->cObj->stdWrap($conf['CHECK.']['layout'], $conf['CHECK.']['layout.']) : $conf['CHECK.']['layout'];
                        if ($checkLayout) {
                            $result = $checkLayout;
                        }
                    }
                    if ($confData['type'] == 'radio') {
                        $radioLayout = isset($conf['RADIO.']['layout.']) ? $this->cObj->stdWrap($conf['RADIO.']['layout'], $conf['RADIO.']['layout.']) : $conf['RADIO.']['layout'];
                        if ($radioLayout) {
                            $result = $radioLayout;
                        }
                    }
                    if ($confData['type'] == 'label') {
                        $labelLayout = isset($conf['LABEL.']['layout.']) ? $this->cObj->stdWrap($conf['LABEL.']['layout'], $conf['LABEL.']['layout.']) : $conf['LABEL.']['layout'];
                        if ($labelLayout) {
                            $result = $labelLayout;
                        }
                    }
                    //RTF
                    $content .= str_replace(
                        [
                            '###FIELD###',
                            '###LABEL###',
                            '###COMMENT###'
                        ],
                        [
                            $fieldCode,
                            $labelCode,
                            $commentCode
                        ],
                        $result
                    );
                }
            }
        }
        if (isset($conf['stdWrap.'])) {
            $content = $this->cObj->stdWrap($content, $conf['stdWrap.']);
        }
        // Redirect (external: where to go afterwards. internal: where to submit to)
        $theRedirect = isset($conf['redirect.']) ? $this->cObj->stdWrap($conf['redirect'], $conf['redirect.']) : $conf['redirect'];
        // redirect should be set to the page to redirect to after an external script has been used. If internal scripts is used, and if no 'type' is set that dictates otherwise, redirect is used as the url to jump to as long as it's an integer (page)
        $target = isset($conf['target.']) ? $this->cObj->stdWrap($conf['target'], $conf['target.']) : $conf['target'];
        // redirect should be set to the page to redirect to after an external script has been used. If internal scripts is used, and if no 'type' is set that dictates otherwise, redirect is used as the url to jump to as long as it's an integer (page)
        $noCache = isset($conf['no_cache.']) ? $this->cObj->stdWrap($conf['no_cache'], $conf['no_cache.']) : $conf['no_cache'];
        // redirect should be set to the page to redirect to after an external script has been used. If internal scripts is used, and if no 'type' is set that dictates otherwise, redirect is used as the url to jump to as long as it's an integer (page)
        $page = $GLOBALS['TSFE']->page;
        // Internal: Just submit to current page
        if (!$theRedirect) {
            $LD = $GLOBALS['TSFE']->tmpl->linkData($page, $target, $noCache, 'index.php', '', $this->cObj->getClosestMPvalueForPage($page['uid']));
        } elseif (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($theRedirect)) {
            // Internal: Submit to page with ID $theRedirect
            $page = $GLOBALS['TSFE']->sys_page->getPage_noCheck($theRedirect);
            $LD = $GLOBALS['TSFE']->tmpl->linkData($page, $target, $noCache, 'index.php', '', $this->cObj->getClosestMPvalueForPage($page['uid']));
        } else {
            // External URL, redirect-hidden field is rendered!
            $LD = $GLOBALS['TSFE']->tmpl->linkData($page, $target, $noCache, '', '', $this->cObj->getClosestMPvalueForPage($page['uid']));
            $LD['totalURL'] = $theRedirect;
            $hiddenfields .= '<input type="hidden" name="redirect" value="' . htmlspecialchars($LD['totalURL']) . '" />';
        }
        // Formtype (where to submit to!):
        if ($propertyOverride['type']) {
            $formtype = $propertyOverride['type'];
        } else {
            $formtype = isset($conf['type.']) ? $this->cObj->stdWrap($conf['type'], $conf['type.']) : $conf['type'];
        }
        // Submit to a specific page
        if (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($formtype)) {
            $page = $GLOBALS['TSFE']->sys_page->getPage_noCheck($formtype);
            $LD_A = $GLOBALS['TSFE']->tmpl->linkData($page, $target, $noCache, '', '', $this->cObj->getClosestMPvalueForPage($page['uid']));
            $action = $LD_A['totalURL'];
        } elseif ($formtype) {
            // Submit to external script
            $LD_A = $LD;
            $action = $formtype;
        } elseif (\TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($theRedirect)) {
            $LD_A = $LD;
            $action = $LD_A['totalURL'];
        } else {
            // Submit to "nothing" - which is current page
            $LD_A = $GLOBALS['TSFE']->tmpl->linkData($GLOBALS['TSFE']->page, $target, $noCache, '', '', $this->cObj->getClosestMPvalueForPage($page['uid']));
            $action = $LD_A['totalURL'];
        }
        // Recipient:
        $theEmail = isset($conf['recipient.']) ? $this->cObj->stdWrap($conf['recipient'], $conf['recipient.']) : $conf['recipient'];
        if ($theEmail && !$GLOBALS['TYPO3_CONF_VARS']['FE']['secureFormmail']) {
            $theEmail = \TYPO3\CMS\Compatibility6\Utility\FormUtility::codeString($theEmail);
            $hiddenfields .= '<input type="hidden" name="recipient" value="' . htmlspecialchars($theEmail) . '" />';
        }
        // location data:
        $location = isset($conf['locationData.']) ? $this->cObj->stdWrap($conf['locationData'], $conf['locationData.']) : $conf['locationData'];
        if ($location) {
            if ($location == 'HTTP_POST_VARS' && isset($_POST['locationData'])) {
                $locationData = GeneralUtility::_POST('locationData');
            } else {
                // locationData is [the page id]:[tablename]:[uid of record]. Indicates on which page the record (from tablename with uid) is shown. Used to check access.
                if (isset($this->data['_LOCALIZED_UID'])) {
                    $locationData = $GLOBALS['TSFE']->id . ':' . str_replace($this->data['uid'], $this->data['_LOCALIZED_UID'], $this->cObj->currentRecord);
                } else {
                    $locationData = $GLOBALS['TSFE']->id . ':' . $this->cObj->currentRecord;
                }
            }
            $hiddenfields .= '<input type="hidden" name="locationData" value="' . htmlspecialchars($locationData) . '" />';
        }
        // Hidden fields:
        if (is_array($conf['hiddenFields.'])) {
            foreach ($conf['hiddenFields.'] as $hF_key => $hF_conf) {
                if (substr($hF_key, -1) != '.') {
                    $hF_value = $this->cObj->cObjGetSingle($hF_conf, $conf['hiddenFields.'][$hF_key . '.'], 'hiddenfields');
                    if ((string)$hF_value !== '' && GeneralUtility::inList('recipient_copy,recipient', $hF_key)) {
                        if ($GLOBALS['TYPO3_CONF_VARS']['FE']['secureFormmail']) {
                            continue;
                        }
                        $hF_value = \TYPO3\CMS\Compatibility6\Utility\FormUtility::codeString($hF_value);
                    }
                    $hiddenfields .= '<input type="hidden" name="' . $hF_key . '" value="' . htmlspecialchars($hF_value) . '" />';
                }
            }
        }
        // Wrap all hidden fields in a div tag (see http://forge.typo3.org/issues/14491)
        $hiddenfields = isset($conf['hiddenFields.']['stdWrap.']) ? $this->cObj->stdWrap($hiddenfields, $conf['hiddenFields.']['stdWrap.']) : '<div style="display:none;">' . $hiddenfields . '</div>';
        if ($conf['REQ']) {
            $goodMess = isset($conf['goodMess.']) ? $this->cObj->stdWrap($conf['goodMess'], $conf['goodMess.']) : $conf['goodMess'];
            $badMess = isset($conf['badMess.']) ? $this->cObj->stdWrap($conf['badMess'], $conf['badMess.']) : $conf['badMess'];
            $emailMess = isset($conf['emailMess.']) ? $this->cObj->stdWrap($conf['emailMess'], $conf['emailMess.']) : $conf['emailMess'];
            $validateForm = ' onsubmit="return validateForm(' . GeneralUtility::quoteJSvalue($formName) . ',' . GeneralUtility::quoteJSvalue(implode(',', $fieldlist)) . ',' . GeneralUtility::quoteJSvalue($goodMess) . ',' . GeneralUtility::quoteJSvalue($badMess) . ',' . GeneralUtility::quoteJSvalue($emailMess) . ')"';
            $GLOBALS['TSFE']->additionalHeaderData['JSFormValidate'] = '<script type="text/javascript" src="' . GeneralUtility::createVersionNumberedFilename(($GLOBALS['TSFE']->absRefPrefix . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath('compatibility6') . 'Resources/Public/JavaScript/jsfunc.validateform.js')) . '"></script>';
        } else {
            $validateForm = '';
        }
        // Create form tag:
        $theTarget = $theRedirect ? $LD['target'] : $LD_A['target'];
        $method = isset($conf['method.']) ? $this->cObj->stdWrap($conf['method'], $conf['method.']) : $conf['method'];
        $content = [
            '<form' . ' action="' . htmlspecialchars($action) . '"' . ' id="' . $formName . '"' . ($xhtmlStrict ? '' : ' name="' . $formName . '"') . ' enctype="multipart/form-data"' . ' method="' . ($method ? $method : 'post') . '"' . ($theTarget ? ' target="' . $theTarget . '"' : '') . $validateForm . '>',
            $hiddenfields . $content,
            '</form>'
        ];
        $arrayReturnMode = isset($conf['arrayReturnMode.']) ? $this->cObj->stdWrap($conf['arrayReturnMode'], $conf['arrayReturnMode.']) : $conf['arrayReturnMode'];
        if ($arrayReturnMode) {
            $content['validateForm'] = $validateForm;
            $content['formname'] = $formName;
            return $content;
        } else {
            return implode('', $content);
        }
    }

    /**
     * Returns a default value for a form field in the FORM cObject.
     * Page CANNOT be cached because that would include the inserted value for the current user.
     *
     * @param bool $noValueInsert If noValueInsert OR if the no_cache flag for this page is NOT set, the original default value is returned.
     * @param string $fieldName The POST var name to get default value for
     * @param string $defaultVal The current default value
     * @return string The default value, either from INPUT var or the current default, based on whether caching is enabled or not.
     */
    protected function getFieldDefaultValue($noValueInsert, $fieldName, $defaultVal)
    {
        if (!$GLOBALS['TSFE']->no_cache || !isset($_POST[$fieldName]) && !isset($_GET[$fieldName]) || $noValueInsert) {
            return $defaultVal;
        } else {
            return GeneralUtility::_GP($fieldName);
        }
    }

    /**
     * Removes forbidden characters and spaces from name/id attributes in the form tag and formfields
     *
     * @param string $name Input string
     * @return string the cleaned string
     */
    protected function cleanFormName($name)
    {
        // Turn data[x][y] into data:x:y:
        $name = preg_replace('/\\[|\\]\\[?/', ':', trim($name));
        // Remove illegal chars like _
        return preg_replace('#[^:a-zA-Z0-9]#', '', $name);
    }
}
