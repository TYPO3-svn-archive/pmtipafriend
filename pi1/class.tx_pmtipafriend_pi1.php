<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 David Keller <dk@puremedia-online.de>
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
***************************************************************/
error_reporting(E_ALL ^ E_NOTICE);
require_once(PATH_tslib.'class.tslib_pibase.php');


/**
 * Plugin 'Tip a friend' for the 'pmtipafriend' extension.
 *
 * @author  David Keller <dk@puremedia-online.de>
 * @author  Marco Ziesing <mz@puremedia-online.de>
 * @package TYPO3
 * @subpackage  tx_pmtipafriend
 */
class tx_pmtipafriend_pi1 extends tslib_pibase
{

  var $prefixId = 'tx_pmtipafriend_pi1';      // Same as class name
  var $scriptRelPath = 'pi1/class.tx_pmtipafriend_pi1.php';   // Path to this script relative to the extension dir.
  var $extKey = 'pmtipafriend';   // The extension key.
  var $pi_checkCHash = TRUE;

  /**
   * The main method of the PlugIn
   *
   * @param   string      $content: The PlugIn content
   * @param   array       $conf: The PlugIn configuration
   * @return  The content that is displayed on the website
   */
  function main($content,$conf)   {
    $this->conf = $conf;
    $this->pi_setPiVarDefaults();
    $this->pi_loadLL();
    $this->pi_initPIflexForm();
    $this->lang = $GLOBALS["TSFE"]->sys_language_uid;

    // flexform-Values
    $this->ffData = array(
            'displayType'   => $this->pi_getFFvalue(
        $this->cObj->data['pi_flexform'],
                'displayType'
      ),
            'templateFile'   => $this->pi_getFFvalue(
        $this->cObj->data['pi_flexform'],
                'templateFile'
      ),
            'mailFormPID'   => $this->pi_getFFvalue(
        $this->cObj->data['pi_flexform'],
                'mailFormPID'
      ),
            'mailText'   => $this->pi_getFFvalue(
        $this->cObj->data['pi_flexform'],
                'mailText'
      ),
            'allowedDomains'   => $this->pi_getFFvalue(
        $this->cObj->data['pi_flexform'],
                'allowedDomains'
      )
    );


    if($this->ffData['displayType'] == 'form') {
      $content = $this->createMailform();
    } else {
      $content = $this->createMailformLink();
    }

    return $this->pi_wrapInBaseClass($content);
  }


  /**
   * Create the link to the mail form
   *
   * @return  string      $content: link to mailform
   */
  function createMailformLink()   {
    $linkParams = array(
      $this->prefixId . '[tipUrl]' => t3lib_div::getIndpEnv('TYPO3_REQUEST_URL')
    );

    $linkPID = !empty($this->ffData['mailFormPID']) ? $this->ffData['mailFormPID'] : $this->conf['mailFormPID'];

    $content = $this->pi_linkToPage($this->pi_getLL('mailFormLinkText'),$linkPID,'',$linkParams);

    return $content;
  }


  /**
   * Create the mail form
   *
   * @return  string      $content: mailform
   */
  function createMailform() {

    if (t3lib_extMgm::isLoaded('captcha')){
      session_start();
      $captchaStr = $_SESSION['tx_captcha_string'];
      $_SESSION['tx_captcha_string'] = '';
    } else {
      $captchaStr = -1;
    }


    // check inputs. if something is wrong, print error message.
    $error_msgs = array();

    $tipUrl = $this->validateUrl($this->piVars['tipUrl']);
    #$tipUrl = 'http://fsz-thueringen.local:8888/index.php';

    if(!empty($this->piVars['submit']) && !eregi("^[a-z0-9]+([-_\.]?[a-z0-9])+@[a-z0-9]+([-_\.]?[a-z0-9])+\.[a-z]{2,4}$", $this->piVars['senderEmail'])) {
      $error_msgs['errorEmailSender'] = $this->pi_getLL('errorEmailSender');
    }

    if(!empty($this->piVars['submit']) && !eregi("^[a-z0-9]+([-_\.]?[a-z0-9])+@[a-z0-9]+([-_\.]?[a-z0-9])+\.[a-z]{2,4}$", $this->piVars['recipientEmail'])) {
      $error_msgs['errorEmailRecipient'] = $this->pi_getLL('errorEmailRecipient');
    }

    if(!empty($this->piVars['submit']) && ($captchaStr != -1 && $this->piVars['captchaResponse'] != $captchaStr)) {
      $error_msgs['errorCaptcha'] = $this->pi_getLL('errorCaptcha');
    }

    if(!empty($this->piVars['submit']) && empty($error_msgs)) {
      $content    = $this->pi_getLL('mailSent');
      $sent       = $this->sendTip($tipUrl);
    } else {
      if($tipUrl != false) {
        // get subpart that wraps the mail form
        $menuWrap = $this->getTemplate('###TEMPLATE_TIP-A-FRIEND_FORM###');

        // get captcha if module is loaded
        $captchaHTMLoutput = t3lib_extMgm::isLoaded('captcha') ? '<img src="'.t3lib_extMgm::siteRelPath('captcha').'captcha/captcha.php" alt="" id="imgCaptcha" />' : '';

        // fill markers with data
        $markerArray['###ERROR_MSG###']             = '';

        foreach($error_msgs AS $key => $value) {
          $markerArray['###ERROR_MSG###']         .= $value . '<br />';
        }

        $markerArray['###FORM_ACTION###']           = $this->pi_getPageLink($GLOBALS['TSFE']->id);

        $markerArray['###LABEL_NAME_RECIPIENT###']  = '<label for="recipientName">' . $this->pi_getLL('labelNameRecipient') . '</label>';
        $markerArray['###FIELD_NAME_RECIPIENT###']  = '<input type="text" name="' . $this->prefixId . '[recipientName]" id="fieldRecipientName" value="' . htmlspecialchars($this->piVars['recipientName']) . '" />';
        $markerArray['###LABEL_EMAIL_RECIPIENT###'] = '<label for="recipientEmail">' . $this->pi_getLL('labelEmailRecipient') . '</label>';
        $markerArray['###FIELD_EMAIL_RECIPIENT###'] = '<input type="text" name="' . $this->prefixId . '[recipientEmail]" id="fieldRecipientEmail" value="' . htmlspecialchars($this->piVars['recipientEmail']) . '" />';

        $markerArray['###LABEL_NAME_SENDER###']     = '<label for="senderName">' . $this->pi_getLL('labelNameSender') . '</label>';
        $markerArray['###FIELD_NAME_SENDER###']     = '<input type="text" name="' . $this->prefixId . '[senderName]" id="fieldSenderName" value="' . htmlspecialchars($this->piVars['senderName']) . '" />';
        $markerArray['###LABEL_EMAIL_SENDER###']    = '<label for="senderEmail">' . $this->pi_getLL('labelEmailSender') . '</label>';
        $markerArray['###FIELD_EMAIL_SENDER###']    = '<input type="text" name="' . $this->prefixId . '[senderEmail]" id="fieldSenderEmail" value="' . htmlspecialchars($this->piVars['senderEmail']) . '" />';

        $markerArray['###LABEL_COMMENT###']         = '<label for="comment">' . $this->pi_getLL('labelComment') . '</label>';
        $markerArray['###FIELD_COMMENT###']         = '<textarea name="' . $this->prefixId . '[comment]" id="fieldComment">' . htmlspecialchars($this->piVars['comment']) . '</textarea>';

        $markerArray['###LABEL_CAPTCHA###']         = '<label for="captcha">' . $this->pi_getLL('labelCaptcha') . '</label>';
        $markerArray['###IMG_CAPTCHA###']           = $captchaHTMLoutput;
        $markerArray['###FIELD_CAPTCHA###']         = '<input type="text" name="' . $this->prefixId . '[captchaResponse]" value="" />';

        $markerArray['###TIPURL###']                = '<input type="hidden" name="' . $this->prefixId . '[tipUrl]" value="' . $tipUrl . '" />';

        $markerArray['###SUBMIT###']                = '<input type="submit" name="' . $this->prefixId . '[submit]" value="' . $this->pi_getLL('labelSubmit') . '" class="buttonSubmit" />';

        $content = $this->cObj->substituteMarkerArrayCached($menuWrap,$markerArray);
      } else {
        $content = $this->pi_getLL('errorUrl');
      }
    }
    return $content;
  }



  function sendTip($url) {

    // build the mail and send it
    $recipientName      = htmlspecialchars($this->piVars['recipientName']);
    $recipientEmail     = htmlspecialchars($this->piVars['recipientEmail']);
    $senderName         = htmlspecialchars($this->piVars['senderName']);
    $senderEmail        = htmlspecialchars($this->piVars['senderEmail']);
    $comment            = htmlspecialchars($this->piVars['comment']);

    $markers            = array('#EMPFAENGER#', '#SENDER#', '#URL#', '#KOMMENTAR#');
    $inputs             = array($recipientName, $senderName, $url, $comment);
    $email['body']      = str_replace($markers, $inputs, $this->ffData['mailText']);

    $email['subject']   = 'Tip-A-Friend';

    $html_start         = '<html><head><title>HTML-Mail</title></head><body>';
    $html_end           = '</body></html>';


    $this->htmlMail = t3lib_div::makeInstance('t3lib_htmlmail');
    $this->htmlMail->start();
    $this->htmlMail->recipient = $recipientName;
    $this->htmlMail->replyto_email = $senderEmail;
    $this->htmlMail->replyto_name = $senderName;
    $this->htmlMail->subject = $email['subject'];
    $this->htmlMail->from_email = $senderEmail;
    $this->htmlMail->from_name = $senderName;
    $this->htmlMail->returnPath = $senderEmail;
    $this->htmlMail->addPlain($email['body']);
    #       $this->htmlMail->setHTML($this->htmlMail->encodeMsg($html_start.$email['body'].$html_end));
    $this->htmlMail->send($recipientEmail);

    $status = 1;
    return $status;
  }



  /**
   * Validate URL
   *
   * @param  string    $url: the given url
   * @return string    $url: the given url or false, if something is wrong
   */
  function validateUrl($url) {
    // remove hmtl tags from url
    $url = strip_tags($url);

    // if the URL contains a '"', unset $url (suspecting XSS code)
    if (strstr($url,'"')) $url = false;

    // in Typo3 >= 4.2 additionally check with removeXSS()
    if($GLOBALS['TYPO3_CONF_VARS']['SYS']['compat_version'] >= 4.2)
    $url = t3lib_div::removeXSS($url);

    // check if domain is allowed
    $domainlist = $this->ffData['allowedDomains'];
    if (eregi("^http:\/\/(www\.)?([^/]+)\/", $url, $reference)) {
      $hostname = $reference[2];
    } else {
      $hostname = false;
    }

    if (stristr($domainlist, $hostname) === FALSE) $url = false;

    return $url;
  }


  /**
   * Substitutes marker
   *
   * @param  array  $singleRecord:
   * @return array  $markerArray: markers from html template
   */
  function getItemMarkerArray($singleRecord) {
    $markerArray = array();

    //local configuration and local cObj
    $lConf = $this->conf['templates.'][$this->conf['templateName'].'.'];
    $lcObj = t3lib_div::makeInstance('tslib_cObj');
    $lcObj->data = $singleRecord;

    return $markerArray;
  }


  /**
   * Get subpart from HTML template
   *
   * @param  string     $template_subPart: Which Subpart to fetch
   * @return string     $subPart: HTML template code
   */
  function getTemplate($template_subPart){
    if(!empty($this->ffData['templateFile'])){
      $templateFile = 'uploads/tx_' . $this->extKey . '/'.$this->ffData['templateFile'];
    } elseif (!empty($this->conf['templateFile'])){
      $templateFile = $this->conf['templateFile'];
    } else {
      die('Template Error!');
    }

    $templateCode = $this->cObj->fileResource($templateFile);

    // Subpart - z.B. ###TEMPLATE_SINGLERECORDS###
    $subPart = $this->cObj->getSubpart(
      $templateCode, $template_subPart
    );
    return $subPart;
  }
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pmtipafriend/pi1/class.tx_pmtipafriend_pi1.php'])  {
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pmtipafriend/pi1/class.tx_pmtipafriend_pi1.php']);
}

?>