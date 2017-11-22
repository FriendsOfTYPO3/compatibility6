<?php
namespace TYPO3\CMS\Compatibility6\Controller\Wizard;

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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * API comments:
 *
 * The form wizard can help you to create forms - it allows you to create almost any kind of HTML form elements and in any order and amount.
 *
 * The format for the resulting configuration code can be either a line-based configuration. That can look like this:
 *
 * Your name: | *name=input | (input your name here!)
 * Your Email: | *email=input
 * Your address: | address=textarea,40,10
 * Your Haircolor: | hair=radio |
 * upload | attachment=file
 * | quoted_printable=hidden | 0
 * | formtype_mail=submit | Send form
 * | html_enabled=hidden
 * | subject=hidden | This is the subject
 *
 *
 * Alternatively it can be XML. The same configuration from above looks like this in XML:
 *
 * <T3FormWizard>
 * <n2>
 * <type>input</type>
 * <label>Your name:</label>
 * <required>1</required>
 * <fieldname>name</fieldname>
 * <size></size>
 * <max></max>
 * <default>(input your name here!)</default>
 * </n2>
 * <n4>
 * <type>input</type>
 * <label>Your Email:</label>
 * <required>1</required>
 * <fieldname>email</fieldname>
 * <size></size>
 * <max></max>
 * <default></default>
 * </n4>
 * <n6>
 * <type>textarea</type>
 * <label>Your address:</label>
 * <fieldname>address</fieldname>
 * <cols>40</cols>
 * <rows>10</rows>
 * <default></default>
 * </n6>
 * <n8>
 * <type>radio</type>
 * <label>Your Haircolor:</label>
 * <fieldname>hair</fieldname>
 * <options></options>
 * </n8>
 * <n10>
 * <type>file</type>
 * <label>upload</label>
 * <fieldname>attachment</fieldname>
 * <size></size>
 * </n10>
 * <n12>
 * <type>hidden</type>
 * <label></label>
 * <fieldname>quoted_printable</fieldname>
 * <default>0</default>
 * </n12>
 * <n2000>
 * <fieldname>formtype_mail</fieldname>
 * <type>submit</type>
 * <default>Send form</default>
 * </n2000>
 * <n2002>
 * <fieldname>html_enabled</fieldname>
 * <type>hidden</type>
 * </n2002>
 * <n2004>
 * <fieldname>subject</fieldname>
 * <type>hidden</type>
 * <default>This is the subject</default>
 * </n2004>
 * <n20>
 * <content></content>
 * </n20>
 * </T3FormWizard>
 *
 *
 * The XML/phpArray structure is the internal format of the wizard.
 */
class FormsController extends \TYPO3\CMS\Backend\Controller\Wizard\AbstractWizardController
{
    /**
     * document template object
     *
     * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
     */
    public $doc;

    /**
     * Content accumulation for the module.
     *
     * @var string
     */
    public $content;

    /**
     * Used to numerate attachments automatically.
     *
     * @var int
     */
    public $attachmentCounter = 0;

    /**
     * If set, the string version of the content is interpreted/written as XML instead of
     * the original linebased kind. This variable still needs binding to the wizard parameters
     * - but support is ready!
     *
     * @var int
     */
    public $xmlStorage = 0;

    /**
     * Wizard parameters, coming from TCEforms linking to the wizard.
     *
     * @var array
     */
    public $P;

    /**
     * The array which is constantly submitted by the multidimensional form of this wizard.
     *
     * @var array
     */
    public $FORMCFG;

    /**
     * Indicates if the form is of a dedicated type, like "formtype_mail" (for tt_content element "Form")
     *
     * @var string
     */
    public $special;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->getLanguageService()->includeLLFile('EXT:compatibility6/Resources/Private/Language/locallang_wizards.xlf');
        $GLOBALS['SOBE'] = $this;

        $this->init();
    }

    /**
     * Initialization the class
     *
     * @return void
     */
    protected function init()
    {
        // GPvars:
        $this->P = GeneralUtility::_GP('P');
        $this->special = GeneralUtility::_GP('special');
        $this->FORMCFG = GeneralUtility::_GP('FORMCFG');
        // Setting options:
        $this->xmlStorage = $this->P['params']['xmlOutput'];
        // Document template object:
        $this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
        $this->doc->setModuleTemplate('EXT:compatibility6/Resources/Private/Templates/Wizard/Forms.html');
        // Setting form tag:
        list($rUri) = explode('#', GeneralUtility::getIndpEnv('REQUEST_URI'));
        $this->doc->form = '<form action="' . htmlspecialchars($rUri) . '" method="post" name="wizardForm">';
    }

    /**
     * Injects the request object for the current request or subrequest
     * As this controller goes only through the main() method, it is rather simple for now
     *
     * @param ServerRequestInterface $request the current request
     * @param ResponseInterface $response
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->main();

        $response->getBody()->write($this->content);
        return $response;
    }

    /**
     * Main function for rendering the form wizard HTML
     *
     * @return void
     */
    public function main()
    {
        if ($this->P['table'] && $this->P['field'] && $this->P['uid']) {
            $this->content .= '<h2>' . $this->getLanguageService()->getLL('forms_title', true) . '</h2><div>' . $this->formsWizard() . '</div>';
        } else {
            $this->content .= '<h2>' . $this->getLanguageService()->getLL('forms_title', true) . '<div><span class="text-danger">' . $this->getLanguageService()->getLL('table_noData', true) . '</span></div>';
        }
        // Setting up the buttons and markers for docheader
        $docHeaderButtons = $this->getButtons();
        $markers['CSH'] = $docHeaderButtons['csh'];
        $markers['CONTENT'] = $this->content;
        // Build the <body> for the module
        $this->content = $this->doc->startPage('Form Wizard');
        $this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
        $this->content .= $this->doc->endPage();
        $this->content = $this->doc->insertStylesAndJS($this->content);
    }

    /**
     * Outputting the accumulated content to screen
     *
     * @return void
     * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8, use mainAction() instead
     */
    public function printContent()
    {
        GeneralUtility::logDeprecatedFunction();
        echo $this->content;
    }

    /**
     * Create the panel of buttons for submitting the form or otherwise perform operations.
     *
     * @return array All available buttons as an assoc. array
     */
    protected function getButtons()
    {
        $buttons = [
            'csh' => '',
            'csh_buttons' => '',
            'close' => '',
            'save' => '',
            'save_close' => '',
            'reload' => ''
        ];
        if ($this->P['table'] && $this->P['field'] && $this->P['uid']) {
            // CSH
            $buttons['csh'] = BackendUtility::cshItem('xMOD_csh_corebe', 'wizard_forms_wiz');
            // CSH Buttons
            $buttons['csh_buttons'] = BackendUtility::cshItem('xMOD_csh_corebe', 'wizard_forms_wiz_buttons');
            // Close
            $buttons['close'] = '<button class="c-inputButton" name="closedok" value="1" title=' . $this->getLanguageService()->sL('LLL:EXT:compatibility6/Resources/Private/Language/locallang.xlf:closeDoc', true) . '>' . $this->iconFactory->getIcon('actions-document-close', Icon::SIZE_SMALL)->render() . '</button>';
            // Save
            $buttons['save'] = '<button class="c-inputButton" name="savedok" value="1" title=' . $this->getLanguageService()->sL('LLL:EXT:compatibility6/Resources/Private/Language/locallang.xlf:saveDoc', true) . '>' . $this->iconFactory->getIcon('actions-document-save', Icon::SIZE_SMALL)->render() . '</button>';
            // Save & Close
            $buttons['save_close'] = '<button class="c-inputButton" name="saveandclosedok" value="1" title=' . $this->getLanguageService()->sL('LLL:EXT:compatibility6/Resources/Private/Language/locallang.xlf:saveCloseDoc', true) . '>' . $this->iconFactory->getIcon('actions-document-save-close', Icon::SIZE_SMALL)->render() . '</button>';
            // Reload
            $buttons['reload'] = '<button class="c-inputButton" name="_refresh" value="1" title="' . $this->getLanguageService()->getLL('forms_refresh', true) . '">' . $this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL)->render() . '</button>';
        }
        return $buttons;
    }

    /**
     * Draws the form wizard content
     *
     * @return string HTML content for the form.
     */
    public function formsWizard()
    {
        if (!$this->checkEditAccess($this->P['table'], $this->P['uid'])) {
            throw new \RuntimeException('Wizard Error: No access', 1385807526);
        }
        // First, check the references by selecting the record:
        $row = BackendUtility::getRecord($this->P['table'], $this->P['uid']);
        if (!is_array($row)) {
            throw new \RuntimeException('Wizard Error: No reference to record', 1294587124);
        }
        // This will get the content of the form configuration code field to us - possibly
        // cleaned up, saved to database etc. if the form has been submitted in the meantime.
        $formCfgArray = $this->getConfigCode($row);
        // Generation of the Form Wizards HTML code:
        $content = $this->getFormHTML($formCfgArray, $row);
        // Return content:
        return $content;
    }

    /****************************
     *
     * Helper functions
     *
     ***************************/
    /**
     * Will get and return the configuration code string
     * Will also save (and possibly redirect/exit) the content if a save button has been pressed
     *
     * @param array $row Current parent record row (passed by value!)
     * @return array Configuration Array
     * @access private
     */
    public function getConfigCode(&$row)
    {
        // If some data has been submitted, then construct
        if (isset($this->FORMCFG['c'])) {
            // Process incoming:
            $this->changeFunc();
            // Convert to string (either line based or XML):
            if ($this->xmlStorage) {
                // Convert the input array to XML:
                $bodyText = GeneralUtility::array2xml_cs($this->FORMCFG['c'], 'T3FormWizard');
                // Setting cfgArr directly from the input:
                $cfgArr = $this->FORMCFG['c'];
            } else {
                // Convert the input array to a string of configuration code:
                $bodyText = $this->cfgArray2CfgString($this->FORMCFG['c']);
                // Create cfgArr from the string based configuration - that way it is cleaned
                // up and any incompatibilities will be removed!
                $cfgArr = $this->cfgString2CfgArray($bodyText);
            }
            // If a save button has been pressed, then save the new field content:
            if (isset($_POST['savedok']) || isset($_POST['saveandclosedok'])) {
                // Make TCEmain object:
                $tce = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
                $tce->stripslashes_values = 0;
                // Put content into the data array:
                $data = [];
                $data[$this->P['table']][$this->P['uid']][$this->P['field']] = $bodyText;
                if ($this->special == 'formtype_mail') {
                    $data[$this->P['table']][$this->P['uid']]['subheader'] = $this->FORMCFG['recipient'];
                }
                // Perform the update:
                $tce->start($data, []);
                $tce->process_datamap();
                // Re-load the record content:
                $row = BackendUtility::getRecord($this->P['table'], $this->P['uid']);
            }
            // If the save/close or close button was pressed, then redirect the screen:
            if (isset($_POST['saveandclosedok']) || isset($_POST['closedok'])) {
                \TYPO3\CMS\Core\Utility\HttpUtility::redirect(GeneralUtility::sanitizeLocalUrl($this->P['returnUrl']));
            }
        } else {
            // If nothing has been submitted, load the $bodyText variable from the selected database row:
            if ($this->xmlStorage) {
                $cfgArr = GeneralUtility::xml2array($row[$this->P['field']]);
            } else {
                // Regular linebased form configuration:
                $cfgArr = $this->cfgString2CfgArray($row[$this->P['field']]);
            }
            $cfgArr = is_array($cfgArr) ? $cfgArr : [];
        }
        // Return configuration code:
        return $cfgArr;
    }

    /**
     * Creates the HTML for the Form Wizard:
     *
     * @param string $formCfgArray Form config array
     * @param array $row Current parent record array
     * @return string HTML for the form wizard
     * @access private
     */
    public function getFormHTML($formCfgArray, $row)
    {
        // Initialize variables:
        $specParts = [];
        $hiddenFields = [];
        $tRows = [];
        // Set header row:
        $cells = [
            $this->getLanguageService()->getLL('forms_preview', true) . ':',
            $this->getLanguageService()->getLL('forms_element', true) . ':',
            $this->getLanguageService()->getLL('forms_config', true) . ':'
        ];
        $tRows[] = '
			<tr id="typo3-formWizardHeader">
				<th>&nbsp;</th>
				<th><strong>' . implode('</strong></th>
				<th><strong>', $cells) . '</strong></th>
			</tr>';
        // Traverse the number of form elements:
        $k = 0;
        foreach ($formCfgArray as $confData) {
            // Initialize:
            $cells = [];
            // If there is a configuration line which is active, then render it:
            if (!isset($confData['comment'])) {
                // Special parts:
                if ($this->special == 'formtype_mail' && GeneralUtility::inList('formtype_mail,subject,html_enabled', $confData['fieldname'])) {
                    $specParts[$confData['fieldname']] = $confData['default'];
                } else {
                    // Render title/field preview COLUMN
                    $cells[] = $confData['type'] != 'hidden' ? '<strong>' . htmlspecialchars($confData['label']) . '</strong>' : '';
                    // Render general type/title COLUMN:
                    $temp_cells = [];
                    // Field type selector:
                    $opt = [];
                    $opt[] = '<option value=""></option>';
                    $types = explode(',', 'input,textarea,select,check,radio,password,file,hidden,submit,property,label');
                    foreach ($types as $t) {
                        $opt[] = '
								<option value="' . $t . '"' . ($confData['type'] == $t ? ' selected="selected"' : '') . '>' . $this->getLanguageService()->getLL(('forms_type_' . $t), true) . '</option>';
                    }
                    $temp_cells[$this->getLanguageService()->getLL('forms_type')] = '
							<select name="FORMCFG[c][' . ($k + 1) * 2 . '][type]">
								' . implode('
								', $opt) . '
							</select>';
                    // Title field:
                    if (!GeneralUtility::inList('hidden,submit', $confData['type'])) {
                        $temp_cells[$this->getLanguageService()->getLL('forms_label')] = '<input type="text"' . $this->doc->formWidth(15) . ' name="FORMCFG[c][' . ($k + 1) * 2 . '][label]" value="' . htmlspecialchars($confData['label']) . '" />';
                    }
                    // Required checkbox:
                    if (!GeneralUtility::inList('check,hidden,submit,label', $confData['type'])) {
                        $temp_cells[$this->getLanguageService()->getLL('forms_required')] = '<input type="checkbox" name="FORMCFG[c][' . ($k + 1) * 2 . '][required]" value="1"' . ($confData['required'] ? ' checked="checked"' : '') . ' title="' . $this->getLanguageService()->getLL('forms_required', true) . '" />';
                    }
                    // Put sub-items together into table cell:
                    $cells[] = $this->formatCells($temp_cells);
                    // Render specific field configuration COLUMN:
                    $temp_cells = [];
                    // Fieldname
                    if ($this->special == 'formtype_mail' && $confData['type'] == 'file') {
                        $confData['fieldname'] = 'attachment' . ++$this->attachmentCounter;
                    }
                    if (!GeneralUtility::inList('label', $confData['type'])) {
                        $temp_cells[$this->getLanguageService()->getLL('forms_fieldName')] = '<input type="text"' . $this->doc->formWidth(10) . ' name="FORMCFG[c][' . ($k + 1) * 2 . '][fieldname]" value="' . htmlspecialchars($confData['fieldname']) . '" title="' . $this->getLanguageService()->getLL('forms_fieldName', true) . '" />';
                    }
                    // Field configuration depending on the fields type:
                    switch ((string)$confData['type']) {
                        case 'textarea':
                            $temp_cells[$this->getLanguageService()->getLL('forms_cols')] = '<input type="text"' . $this->doc->formWidth(5) . ' name="FORMCFG[c][' . ($k + 1) * 2 . '][cols]" value="' . htmlspecialchars($confData['cols']) . '" title="' . $this->getLanguageService()->getLL('forms_cols', true) . '" />';
                            $temp_cells[$this->getLanguageService()->getLL('forms_rows')] = '<input type="text"' . $this->doc->formWidth(5) . ' name="FORMCFG[c][' . ($k + 1) * 2 . '][rows]" value="' . htmlspecialchars($confData['rows']) . '" title="' . $this->getLanguageService()->getLL('forms_rows', true) . '" />';
                            $temp_cells[$this->getLanguageService()->getLL('forms_extra')] = '<input type="checkbox" name="FORMCFG[c][' . ($k + 1) * 2 . '][extra]" value="OFF"' . ($confData['extra'] == 'OFF' ? ' checked="checked"' : '') . ' title="' . $this->getLanguageService()->getLL('forms_extra', true) . '" />';
                            break;
                        case 'input':

                        case 'password':
                            $temp_cells[$this->getLanguageService()->getLL('forms_size')] = '<input type="text"' . $this->doc->formWidth(5) . ' name="FORMCFG[c][' . ($k + 1) * 2 . '][size]" value="' . htmlspecialchars($confData['size']) . '" title="' . $this->getLanguageService()->getLL('forms_size', true) . '" />';
                            $temp_cells[$this->getLanguageService()->getLL('forms_max')] = '<input type="text"' . $this->doc->formWidth(5) . ' name="FORMCFG[c][' . ($k + 1) * 2 . '][max]" value="' . htmlspecialchars($confData['max']) . '" title="' . $this->getLanguageService()->getLL('forms_max', true) . '" />';
                            break;
                        case 'file':
                            $temp_cells[$this->getLanguageService()->getLL('forms_size')] = '<input type="text"' . $this->doc->formWidth(5) . ' name="FORMCFG[c][' . ($k + 1) * 2 . '][size]" value="' . htmlspecialchars($confData['size']) . '" title="' . $this->getLanguageService()->getLL('forms_size', true) . '" />';
                            break;
                        case 'select':
                            $temp_cells[$this->getLanguageService()->getLL('forms_size')] = '<input type="text"' . $this->doc->formWidth(5) . ' name="FORMCFG[c][' . ($k + 1) * 2 . '][size]" value="' . htmlspecialchars($confData['size']) . '" title="' . $this->getLanguageService()->getLL('forms_size', true) . '" />';
                            $temp_cells[$this->getLanguageService()->getLL('forms_autosize')] = '<input type="checkbox" name="FORMCFG[c][' . ($k + 1) * 2 . '][autosize]" value="1"' . ($confData['autosize'] ? ' checked="checked"' : '') . ' title="' . $this->getLanguageService()->getLL('forms_autosize', true) . '" />';
                            $temp_cells[$this->getLanguageService()->getLL('forms_multiple')] = '<input type="checkbox" name="FORMCFG[c][' . ($k + 1) * 2 . '][multiple]" value="1"' . ($confData['multiple'] ? ' checked="checked"' : '') . ' title="' . $this->getLanguageService()->getLL('forms_multiple', true) . '" />';
                            break;
                    }
                    // Field configuration depending on the fields type:
                    switch ((string)$confData['type']) {
                        case 'textarea':

                        case 'input':

                        case 'password':
                            if (trim($confData['specialEval']) !== '') {
                                $hiddenFields[] = '<input type="hidden" name="FORMCFG[c][' . ($k + 1) * 2 . '][specialEval]" value="' . htmlspecialchars($confData['specialEval']) . '" />';
                            }
                            break;
                    }
                    // Default data
                    if ($confData['type'] == 'select' || $confData['type'] == 'radio') {
                        $temp_cells[$this->getLanguageService()->getLL('forms_options')] = '<textarea ' . $this->doc->formWidth(15) . ' rows="4" name="FORMCFG[c][' . ($k + 1) * 2 . '][options]" title="' . $this->getLanguageService()->getLL('forms_options', true) . '">' . htmlspecialchars($confData['default']) . '</textarea>';
                    } elseif ($confData['type'] == 'check') {
                        $temp_cells[$this->getLanguageService()->getLL('forms_checked')] = '<input type="checkbox" name="FORMCFG[c][' . ($k + 1) * 2 . '][default]" value="1"' . (trim($confData['default']) ? ' checked="checked"' : '') . ' title="' . $this->getLanguageService()->getLL('forms_checked', true) . '" />';
                    } elseif ($confData['type'] && $confData['type'] != 'file') {
                        $temp_cells[$this->getLanguageService()->getLL('forms_default')] = '<input type="text"' . $this->doc->formWidth(15) . ' name="FORMCFG[c][' . ($k + 1) * 2 . '][default]" value="' . htmlspecialchars($confData['default']) . '" title="' . $this->getLanguageService()->getLL('forms_default', true) . '" />';
                    }
                    $cells[] = $confData['type'] ? $this->formatCells($temp_cells) : '';
                    // CTRL panel for an item (move up/down/around):
                    $ctrl = '';
                    $onClick = 'document.wizardForm.action+=\'#ANC_' . (($k + 1) * 2 - 2) . '\';';
                    $onClick = ' onclick="' . htmlspecialchars($onClick) . '"';
                    // @todo $inputStyle undefined
                    $brTag = $inputStyle ? '' : '<br />';
                    if ($k != 1) {
                        $ctrl .= '<button name="FORMCFG[row_top][' . ($k + 1) * 2 . ']"' . $onClick . ' title="' . $this->getLanguageService()->getLL('table_top', true) . '">' . $this->iconFactory->getIcon('actions-move-to-top', Icon::SIZE_SMALL)->render() . '</button>' . $brTag;
                        $ctrl .= '<button name="FORMCFG[row_up][' . ($k + 1) * 2 . ']"' . $onClick . ' title="' . $this->getLanguageService()->getLL('table_up', true) . '">' . $this->iconFactory->getIcon('actions-move-up', Icon::SIZE_SMALL)->render() . '</button>' . $brTag;
                    }
                    $ctrl .= '<button name="FORMCFG[row_remove][' . ($k + 1) * 2 . ']" ' . $onClick . ' title = "' . $this->getLanguageService()->getLL('table_removeRow', true) . '">' . $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL)->render() . '</button>' . $brTag;

                    if ($k != (count($formCfgArray)/2)) {
                        $ctrl .= '<button name="FORMCFG[row_down][' . ($k + 1) * 2 . ']"' . $onClick . ' title="' . $this->getLanguageService()->getLL('table_down', true) . '">' . $this->iconFactory->getIcon('actions-move-down', Icon::SIZE_SMALL)->render() . '</button>' . $brTag;
                        $ctrl .= '<button name="FORMCFG[row_bottom][' . ($k + 1) * 2 . ']"' . $onClick . ' title="' . $this->getLanguageService()->getLL('table_bottom', true) . '">' . $this->iconFactory->getIcon('actions-move-to-bottom', Icon::SIZE_SMALL)->render() . '</button>' . $brTag;
                    }

                    $ctrl .= '<button name="FORMCFG[row_add][' . ($k + 1) * 2 . ']"' . $onClick . ' title="' . $this->getLanguageService()->getLL('table_addRow', true) . '">' . $this->iconFactory->getIcon('actions-template-new', Icon::SIZE_SMALL)->render() . '</button>' . $brTag;
                    $ctrl = '<span class="c-wizButtonsV">' . $ctrl . '</span>';
                    // Finally, put together the full row from the generated content above:
                    $tRows[] = '
						<tr>
							<td><a name="ANC_' . ($k + 1) * 2 . '"></a>' . $ctrl . '</td>
							<td>' . implode('</td>
							<td valign="top">', $cells) . '</td>
						</tr>';
                }
            } else {
                $hiddenFields[] = '<input type="hidden" name="FORMCFG[c][' . ($k + 1) * 2 . '][comment]" value="' . htmlspecialchars($confData['comment']) . '" />';
            }
            // Increment counter:
            $k++;
        }
        // If the form is of the special type "formtype_mail" (used for tt_content elements):
        if ($this->special == 'formtype_mail') {
            // Blank spacer:
            $tRows[] = '
				<tr>
					<td colspan="4">&nbsp;</td>
				</tr>';
            // Header:
            $tRows[] = '
				<tr>
					<th colspan="4"><h4>' . $this->getLanguageService()->getLL('forms_special_eform', true) . ': ' . BackendUtility::cshItem('xMOD_csh_corebe', 'wizard_forms_wiz_formmail_info') . '</h4></th>
				</tr>';
            // "FORM type":
            $tRows[] = '
				<tr>
					<td colspan="2">&nbsp;</td>
					<td>' . $this->getLanguageService()->getLL('forms_eform_formtype_mail', true) . ':</td>
					<td>
						<input type="hidden" name="FORMCFG[c][' . 1000 * 2 . '][fieldname]" value="formtype_mail" />
						<input type="hidden" name="FORMCFG[c][' . 1000 * 2 . '][type]" value="submit" />
						<input type="text"' . $this->doc->formWidth(15) . ' name="FORMCFG[c][' . 1000 * 2 . '][default]" value="' . htmlspecialchars($specParts['formtype_mail']) . '" />
					</td>
				</tr>';
            // "Send HTML mail":
            $tRows[] = '
				<tr>
					<td colspan="2">&nbsp;</td>
					<td>' . $this->getLanguageService()->getLL('forms_eform_html_enabled', true) . ':</td>
					<td>
						<input type="hidden" name="FORMCFG[c][' . 1001 * 2 . '][fieldname]" value="html_enabled" />
						<input type="hidden" name="FORMCFG[c][' . 1001 * 2 . '][type]" value="hidden" />
						<input type="checkbox" name="FORMCFG[c][' . 1001 * 2 . '][default]" value="1"' . ($specParts['html_enabled'] ? ' checked="checked"' : '') . ' />
					</td>
				</tr>';
            // "Subject":
            $tRows[] = '
				<tr>
					<td colspan="2">&nbsp;</td>
					<td>' . $this->getLanguageService()->getLL('forms_eform_subject', true) . ':</td>
					<td>
						<input type="hidden" name="FORMCFG[c][' . 1002 * 2 . '][fieldname]" value="subject" />
						<input type="hidden" name="FORMCFG[c][' . 1002 * 2 . '][type]" value="hidden" />
						<input type="text"' . $this->doc->formWidth(15) . ' name="FORMCFG[c][' . 1002 * 2 . '][default]" value="' . htmlspecialchars($specParts['subject']) . '" />
					</td>
				</tr>';
            // Recipient:
            $tRows[] = '
				<tr>
					<td colspan="2">&nbsp;</td>
					<td>' . $this->getLanguageService()->getLL('forms_eform_recipient', true) . ':</td>
					<td>
						<input type="text"' . $this->doc->formWidth(15) . ' name="FORMCFG[recipient]" value="' . htmlspecialchars($row['subheader']) . '" />
					</td>
				</tr>';
        }
        $content = '';
        // Implode all table rows into a string, wrapped in table tags.
        $content .= '

			<!--
				Form wizard
			-->
			<table class="table table-bordered table-condensed" id="typo3-formwizard">
				' . implode('', $tRows) . '
			</table>';
        // Add hidden fields:
        $content .= implode('', $hiddenFields);
        // Return content:
        return $content;
    }

    /**
     * Detects if a control button (up/down/around/delete) has been pressed for an item and accordingly it will manipulate the internal FORMCFG array
     *
     * @return void
     * @access private
     */
    public function changeFunc()
    {
        if ($this->FORMCFG['row_remove']) {
            $kk = key($this->FORMCFG['row_remove']);
            $cmd = 'row_remove';
        } elseif ($this->FORMCFG['row_add']) {
            $kk = key($this->FORMCFG['row_add']);
            $cmd = 'row_add';
        } elseif ($this->FORMCFG['row_top']) {
            $kk = key($this->FORMCFG['row_top']);
            $cmd = 'row_top';
        } elseif ($this->FORMCFG['row_bottom']) {
            $kk = key($this->FORMCFG['row_bottom']);
            $cmd = 'row_bottom';
        } elseif ($this->FORMCFG['row_up']) {
            $kk = key($this->FORMCFG['row_up']);
            $cmd = 'row_up';
        } elseif ($this->FORMCFG['row_down']) {
            $kk = key($this->FORMCFG['row_down']);
            $cmd = 'row_down';
        }
        if ($cmd && \TYPO3\CMS\Core\Utility\MathUtility::canBeInterpretedAsInteger($kk)) {
            if (substr($cmd, 0, 4) == 'row_') {
                switch ($cmd) {
                    case 'row_remove':
                        unset($this->FORMCFG['c'][$kk]);
                        break;
                    case 'row_add':
                        $this->FORMCFG['c'][$kk + 1] = [];
                        break;
                    case 'row_top':
                        $this->FORMCFG['c'][1] = $this->FORMCFG['c'][$kk];
                        unset($this->FORMCFG['c'][$kk]);
                        break;
                    case 'row_bottom':
                        $this->FORMCFG['c'][1000000] = $this->FORMCFG['c'][$kk];
                        unset($this->FORMCFG['c'][$kk]);
                        break;
                    case 'row_up':
                        $this->FORMCFG['c'][$kk - 3] = $this->FORMCFG['c'][$kk];
                        unset($this->FORMCFG['c'][$kk]);
                        break;
                    case 'row_down':
                        $this->FORMCFG['c'][$kk + 3] = $this->FORMCFG['c'][$kk];
                        unset($this->FORMCFG['c'][$kk]);
                        break;
                }
                ksort($this->FORMCFG['c']);
            }
        }
    }

    /**
     * Converts the input array to a configuration code string
     *
     * @param array $cfgArr Array of form configuration (follows the input structure from the form wizard POST form)
     * @return string The array converted into a string with line-based configuration.
     * @see cfgString2CfgArray()
     */
    public function cfgArray2CfgString($cfgArr)
    {
        // Initialize:
        $inLines = [];
        // Traverse the elements of the form wizard and transform the settings into configuration code.
        foreach ($cfgArr as $vv) {
            // If "content" is found, then just pass it over.
            if ($vv['comment']) {
                $inLines[] = trim($vv['comment']);
            } else {
                // Begin to put together the single-line configuration code of this field:
                // Reset:
                $thisLine = [];
                // Set Label:
                $thisLine[0] = str_replace('|', '', $vv['label']);
                // Set Type:
                if ($vv['type']) {
                    $thisLine[1] = ($vv['required'] ? '*' : '') . str_replace(',', '', (($vv['fieldname'] ? $vv['fieldname'] . '=' : '') . $vv['type']));
                    // Default:
                    $tArr = ['', '', '', '', '', ''];
                    switch ((string)$vv['type']) {
                        case 'textarea':
                            if ((int)$vv['cols']) {
                                $tArr[0] = (int)$vv['cols'];
                            }
                            if ((int)$vv['rows']) {
                                $tArr[1] = (int)$vv['rows'];
                            }
                            if (trim($vv['extra'])) {
                                $tArr[2] = trim($vv['extra']);
                            }
                            if ($vv['specialEval'] !== '') {
                                // Preset blank default value so position 3 can get a value...
                                $thisLine[2] = '';
                                $thisLine[3] = $vv['specialEval'];
                            }
                            break;
                        case 'input':
                        case 'password':
                            if ((int)$vv['size']) {
                                $tArr[0] = (int)$vv['size'];
                            }
                            if ((int)$vv['max']) {
                                $tArr[1] = (int)$vv['max'];
                            }
                            if ($vv['specialEval'] !== '') {
                                // Preset blank default value so position 3 can get a value...
                                $thisLine[2] = '';
                                $thisLine[3] = $vv['specialEval'];
                            }
                            break;
                        case 'file':
                            if ((int)$vv['size']) {
                                $tArr[0] = (int)$vv['size'];
                            }
                            break;
                        case 'select':
                            if ((int)$vv['size']) {
                                $tArr[0] = (int)$vv['size'];
                            }
                            if ($vv['autosize']) {
                                $tArr[0] = 'auto';
                            }
                            if ($vv['multiple']) {
                                $tArr[1] = 'm';
                            }
                            break;
                    }
                    $tArr = $this->cleanT($tArr);
                    if (!empty($tArr)) {
                        $thisLine[1] .= ',' . implode(',', $tArr);
                    }
                    $thisLine[1] = str_replace('|', '', $thisLine[1]);
                    // Default:
                    if ($vv['type'] == 'select' || $vv['type'] == 'radio') {
                        $options = str_replace(',', '', $vv['options']);
                        $options = str_replace(
                            [CRLF, CR, LF],
                            ', ',
                            $options
                        );
                        $thisLine[2] = $options;
                    } elseif ($vv['type'] == 'check') {
                        if ($vv['default']) {
                            $thisLine[2] = 1;
                        }
                    } elseif (trim($vv['default']) !== '') {
                        $thisLine[2] = $vv['default'];
                    }
                    if (isset($thisLine[2])) {
                        $thisLine[2] = str_replace('|', '', $thisLine[2]);
                    }
                }
                // Compile the final line:
                $inLines[] = preg_replace('/[

]*/', '', implode(' | ', $thisLine));
            }
        }
        // Finally, implode the lines into a string, and return it:
        return implode(LF, $inLines);
    }

    /**
     * Converts the input configuration code string into an array
     *
     * @param string $cfgStr Configuration code
     * @return array Configuration array
     * @see cfgArray2CfgString()
     */
    public function cfgString2CfgArray($cfgStr)
    {
        // Traverse the number of form elements:
        $tLines = explode(LF, $cfgStr);
        $attachmentCounter = 0;
        foreach ($tLines as $k => $v) {
            // Initialize:
            $confData = [];
            $val = trim($v);
            // Accept a line as configuration if a) it is blank(! - because blank lines indicates new,
            // unconfigured fields) or b) it is NOT a comment.
            if (!$val || strcspn($val, '#/')) {
                // Split:
                $parts = GeneralUtility::trimExplode('|', $val);
                // Label:
                $confData['label'] = trim($parts[0]);
                // Field:
                $fParts = GeneralUtility::trimExplode(',', $parts[1]);
                $fParts[0] = trim($fParts[0]);
                if ($fParts[0][0] === '*') {
                    $confData['required'] = 1;
                    $fParts[0] = substr($fParts[0], 1);
                }
                $typeParts = GeneralUtility::trimExplode('=', $fParts[0]);
                $confData['type'] = trim(strtolower(end($typeParts)));
                if ($confData['type']) {
                    if (count($typeParts) === 1) {
                        $confData['fieldname'] = substr(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', trim($parts[0]))), 0, 30);
                        // Attachment names...
                        if ($confData['type'] == 'file') {
                            $confData['fieldname'] = 'attachment' . $attachmentCounter;
                            $attachmentCounter = (int)$attachmentCounter + 1;
                        }
                    } else {
                        $confData['fieldname'] = str_replace(' ', '_', trim($typeParts[0]));
                    }
                    switch ((string)$confData['type']) {
                        case 'select':
                        case 'radio':
                            $confData['default'] = implode(LF, GeneralUtility::trimExplode(',', $parts[2]));
                            break;
                        default:
                            $confData['default'] = trim($parts[2]);
                    }
                    // Field configuration depending on the fields type:
                    switch ((string)$confData['type']) {
                        case 'textarea':
                            $confData['cols'] = $fParts[1];
                            $confData['rows'] = $fParts[2];
                            $confData['extra'] = strtoupper($fParts[3]) == 'OFF' ? 'OFF' : '';
                            $confData['specialEval'] = trim($parts[3]);
                            break;
                        case 'input':
                        case 'password':
                            $confData['size'] = $fParts[1];
                            $confData['max'] = $fParts[2];
                            $confData['specialEval'] = trim($parts[3]);
                            break;
                        case 'file':
                            $confData['size'] = $fParts[1];
                            break;
                        case 'select':
                            $confData['size'] = (int)$fParts[1] ? $fParts[1] : '';
                            $confData['autosize'] = strtolower(trim($fParts[1])) === 'auto' ? 1 : 0;
                            $confData['multiple'] = strtolower(trim($fParts[2])) === 'm' ? 1 : 0;
                            break;
                    }
                }
            } else {
                // No configuration, only a comment:
                $confData = [
                    'comment' => $val
                ];
            }
            // Adding config array:
            $cfgArr[] = $confData;
        }
        // Return cfgArr
        return $cfgArr;
    }

    /**
     * Removes any "trailing elements" in the array which consists of whitespace (little like trim() does for strings, so this does for arrays)
     *
     * @param array $tArr Single dim array
     * @return array Processed array
     * @access private
     */
    public function cleanT($tArr)
    {
        for ($a = count($tArr); $a > 0; $a--) {
            if ((string)$tArr[$a - 1] !== '') {
                break;
            } else {
                unset($tArr[$a - 1]);
            }
        }
        return $tArr;
    }

    /**
     * Wraps items in $fArr in table cells/rows, displaying them vertically.
     *
     * @param array $fArr Array of label/HTML pairs.
     * @return string HTML table
     * @access private
     */
    public function formatCells($fArr)
    {
        // Traverse the elements in $fArr and wrap them in table cells:
        $lines = [];
        foreach ($fArr as $l => $c) {
            $lines[] = '
				<tr>
					<td nowrap="nowrap">' . htmlspecialchars(($l . ':')) . '&nbsp;</td>
					<td>' . $c . '</td>
				</tr>';
        }
        $lines[] = '
			<tr>
				<td colspan="2"></td>
			</tr>';
        // Wrap in table and return:
        return '
			<table>
				' . implode('', $lines) . '
			</table>';
    }
}
