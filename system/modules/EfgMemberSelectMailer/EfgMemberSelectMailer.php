<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Cliff Parnitzky 2012-2013
 * @author     Cliff Parnitzky
 * @package    EfgMemberSelectMailer
 * @license    LGPL
 */

class EfgMemberSelectMailer extends Frontend {
	/**
	 * Check if mails should be send and execute it.
	 */
	public function processEfgFormData($arrSubmitted, $arrFiles, $intOldId, $arrForm, $arrLabels=null) {
		if ($arrForm['efgMemberSelectMailerActive']) {
			if (!$arrForm['efgMemberSelectMailerConfirmSendMailActive'] || (strlen($arrForm['efgMemberSelectMailerConfirmSendMailFormField']) > 0 && $arrSubmitted[$arrForm['efgMemberSelectMailerConfirmSendMailFormField']] == $arrForm['efgMemberSelectMailerConfirmSendMailValue'])) {
				$memberIds = $arrSubmitted[$arrForm['efgMemberSelectMailerMemberFormField']];
				if (is_array($memberIds)) {
					$memberIds = implode(", ", $memberIds);
				};
				
				if (strlen($memberIds) > 0) {
					$member = $this->Database->prepare("SELECT * FROM tl_member WHERE id IN (" . $memberIds . ")")
												 ->execute();
					while ($member->next()) {
						$this->sendMail($member, $arrForm, $arrSubmitted, $intOldId == 0);
					}
				}
			}
		}
		
		return $arrSubmitted;
	}
	
	/**
	 * Sending the email
	 */
	private function sendMail($member, $arrForm, $post, $isNew) {
		// first check if required extension 'ExtendedEmailRegex' is installed
		if (!in_array('extendedEmailRegex', $this->Config->getActiveModules())) {
			$this->log('EfgMemberSelectMailer: Extension "ExtendedEmailRegex" is required!', 'EfgMemberSelectMailer sendMail()', TL_ERROR);
			return false;
		}
		$this->import('ExtendedEmailRegex', 'Base');
		
		$objEmail = new Email();
		$objEmail->logFile = 'EfgMemberSelectMailer.log';
		$objEmail->from = $arrForm['efgMemberSelectMailerMailSenderEmail'];
		if (strlen($arrForm['efgMemberSelectMailerMailSenderName']) > 0) {
			$objEmail->fromName = $arrForm['efgMemberSelectMailerMailSenderName'];
		}
		$objEmail->subject = $this->replaceEmailInsertTags($arrForm['efgMemberSelectMailerMailSubject'], $member, $arrForm, $post, $isNew);
		$objEmail->html = $this->replaceEmailInsertTags($arrForm['efgMemberSelectMailerMailText'], $member, $arrForm, $post, $isNew);
		$objEmail->text = $this->transformEmailHtmlToText($objEmail->html);
		
		try {
			$emailTo = $member->email;
			
			if ($GLOBALS['TL_CONFIG']['efgMemberSelectMailerDeveloperMode']) {
				$emailTo = $GLOBALS['TL_CONFIG']['efgMemberSelectMailerDeveloperModeEmail'];
			} else {
				if (strlen($arrForm['efgMemberSelectMailerMailCopy']) > 0) {
					$emailCC = ExtendedEmailRegex::getEmailsFromList($arrForm['efgMemberSelectMailerMailCopy']);
					$objEmail->sendCc($emailCC);
				}
				
				if (strlen($arrForm['efgMemberSelectMailerMailBlindCopy']) > 0) {
					$emailBCC = ExtendedEmailRegex::getEmailsFromList($arrForm['efgMemberSelectMailerMailBlindCopy']);
					$objEmail->sendBcc($emailBCC);
				}
				
				$emailTo = $member->email;
			}
			return $objEmail->sendTo($emailTo);
		} catch (Swift_RfcComplianceException $e) {
			$this->log("Mail could not be send: " . $e->getMessage(), "EfgMemberSelectMailer sendMail()", TL_ERROR);
			return false;
		}
	}	
	/**
	 * Replaces all insert tags for the email text.
	 */
	private function replaceEmailInsertTags ($text, $member, $arrForm, $post, $isNew) {
		$textArray = preg_split('/\{\{([^\}]+)\}\}/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		
		for ($count = 0; $count < count($textArray); $count++) {
			$parts = explode("::", $textArray[$count]);
			if ($parts[0] == "member") {
				if ($parts[1] == "password") {
					$textArray[$count] = '';
				} else if ($parts[1] == "dateOfBirth") {
					$textArray[$count] = date($GLOBALS['TL_CONFIG']['dateFormat'], $member->$parts[1]);
				} else if ($parts[1] == "gender") {
					$textArray[$count] = $GLOBALS['TL_LANG']['MSC'][$member->$parts[1]];
				} else if ($parts[1] == "name") {
					$textArray[$count] = $member->firstname . " " . $member->lastname;
				} else {
					$textArray[$count] = $member->$parts[1];
				}
			} else if ($parts[0] == "sender") {
				switch ($parts[1]) {
						case 'email': $textArray[$count] = $arrForm['efgMemberSelectMailerMailSenderEmail']; break;
						case 'name': $textArray[$count] = $arrForm['efgMemberSelectMailerMailSenderName']; break;
				} 
			} else if ($parts[0] == "post") {
				if (is_array($post[$parts[1]])) {
					$textArray[$count] = implode(", ", $post[$parts[1]]);
				} else {
					$textArray[$count] = $post[$parts[1]];
				}
			} else if ($parts[0] == "user") {
				if (FE_USER_LOGGED_IN) {
					$this->import('FrontendUser', 'User');
					if ($parts[1] == "password") {
						$textArray[$count] = '';
					} else if ($parts[1] == "dateOfBirth") {
						$textArray[$count] = date($GLOBALS['TL_CONFIG']['dateFormat'], $this->User->$parts[1]);
					} else if ($parts[1] == "gender") {
						$textArray[$count] = $GLOBALS['TL_LANG']['MSC'][$this->User->$parts[1]];
					} else if ($parts[1] == "name") {
						$textArray[$count] = $this->User->firstname . " " . $this->User->lastname;
					} else {
						$textArray[$count] = $this->User->$parts[1];
					}
				} 
			} else if ($parts[0] == "ifnew") {
				if (count($textArray) > $count + 2) {
					$partsNext = explode("::", $textArray[$count + 2]);
					if ($textArray[$count + 2] == "endif") {
						// we have {{ifnew}}text{{endif}}
						if ($isNew) {
							$textArray[$count] = $textArray[$count + 1];
						} else {
							$textArray[$count] = "";
						}
						$textArray[$count + 1] = "";
						$textArray[$count + 2] = "";
						$count = $count + 2;
					} else if ($textArray[$count + 2] == "else" && $textArray[$count + 4] == "endif") {
						// we have {{ifnew}}text{{else}}other text{{endif}}
						if ($isNew) {
							$textArray[$count] = $textArray[$count + 1];
						} else {
							$textArray[$count] = $textArray[$count + 3];
						}
						$textArray[$count + 1] = "";
						$textArray[$count + 2] = "";
						$textArray[$count + 3] = "";
						$textArray[$count + 4] = "";
						$count = $count + 4;
					}
				}
			}
		}
		
		return implode('', $textArray);
	}
	
	
	/**
	 * Creates the text from the html for the email.
	 */
	private function transformEmailHtmlToText ($emailHtml) {
		$emailText = $emailHtml;
		$emailText = str_replace("</p> ", "\n\n", $emailText);
		$emailText = str_replace("</ul> ", "\n", $emailText);
		$emailText = str_replace(" <li>", " - ", $emailText);
		$emailText = str_replace("</li>", "\n", $emailText);
		$emailText = strip_tags($emailText);
		return $emailText;
	}
}

?>