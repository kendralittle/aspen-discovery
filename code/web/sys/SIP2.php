<?php
/**
 * SIP2 Class
 *
 * This class provides a method of communicating with an Integrated
 * Library System using 3M's SIP2 standard.
 *
 * @author     John Wohlers <john@wohlershome.net>
 * @licence    http://opensource.org/licenses/gpl-3.0.html
 * @package
 * @copyright  John Wohlers <john@wohlershome.net>
 * @version    $Id: sip2.class.php 26 2008-04-21 17:25:51Z cap60552 $
 * @link       http://php-sip2.googlecode.com/
 */

/**
 *  2008.04.11
 *  Incorporate a bug fix submitted by Bob Wicksall
 *
 *  TODO
 *   - Clean up variable names, check for consistency
 *   - Add better i18n support, including functions to handle the SIP2 language definitions
 *
 */

/**
 * General Usage:
 *    include('sip2.class.php');
 *
 *    // create object
 *    $mysip = new sip2;
 *
 *    // Set host name
 *    $mysip->hostname = 'server.example.com';
 *    $mysip->port = 6002;
 *
 *    // Identify a patron
 *    $mysip->patron = '101010101';
 *    $mysip->patronpwd = '010101';
 *
 *    // connect to SIP server
 *    $result = $mysip->connect();
 *
 *    // selfcheck status message goes here...
 *
 *
 *    // Get Charged Items Raw response
 *    $in = $mysip->msgPatronInformation('charged');
 *
 *    // parse the raw response into an array
 *    $result = $mysip->parsePatronInfoResponse( $mysip->get_message($in) );
 *
 */
class sip2 {

	/* Public variables for configuration */
	public $hostname;
	public $port = 6002; /* default sip2 port for Sirsi */
	public $library = '';
	public $language = '001'; /* 001= english */

	var $use_usleep = 1;    // change to 1 for faster execution
	// don't change to 1 on Windows servers unless you have PHP 5
	var $sleeptime = 175000;
	var $loginsleeptime = 1000000;

	/* Patron ID */
	public $patron = ''; /* AA */
	public $patronpwd = ''; /* AD */

	/*terminal password */
	public $AC = ''; /*AC */

	/* Patron identifier */
	public $AA;

	/* Maximum number of resends allowed before get_message gives up */
	public $maxretry = 3;

	/* Terminator s */
	public $fldTerminator = '|';
	public $msgTerminator = "\r\n";

	/* Login Variables */
	public $UIDalgorithm = 0;   /* 0    = unencrypted, default */
	public $PWDalgorithm = 0;   /* undefined in documentation */
	public $scLocation = '';  /* Location Code */

	/* Debug */
	public $debug = false;

	/* Private variables for building messages */
	public $AO = 'WohlersSIP';
	public $AN = 'SIPCHK';

	/* Private variable to hold socket connection */
	private $socket;

	/* Sequence number counter */
	private $seq = -1;

	/* resend counter */
	private $retry = 0;

	/* Workarea for building a message */
	private $msgBuild = '';
	private $noFixed = false;

	function msgPatronStatusRequest() {
		/* Server Response: Patron Status Response message. */
		$this->_newMessage('23');
		$this->_addFixedOption($this->language, 3);
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AA', $this->patron);
		$this->_addVarOption('AC', $this->AC);
		$this->_addVarOption('AD', $this->patronpwd);
		return $this->_returnMessage();
	}

	function msgCheckout($item, $nbDateDue = '', $scRenewal = 'N', $itmProp = '', $fee = 'N', $noBlock = 'N', $cancel = 'N') {
		/* Checkout an item  (11) - untested */
		$this->_newMessage('11');
		$this->_addFixedOption($scRenewal, 1);
		$this->_addFixedOption($noBlock, 1);
		$this->_addFixedOption($this->_datestamp(), 18);
		if ($nbDateDue != '') {
			/* override defualt date due */
			$this->_addFixedOption($this->_datestamp($nbDateDue), 18);
		} else {
			/* send a blank date due to allow ACS to use default date due computed for item */
			$this->_addFixedOption('', 18);
		}
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AA', $this->patron);
		$this->_addVarOption('AB', $item);
		$this->_addVarOption('AC', $this->AC);
		$this->_addVarOption('CH', $itmProp, true);
		$this->_addVarOption('AD', $this->patronpwd, true);
		$this->_addVarOption('BO', $fee, true); /* Y or N */
		$this->_addVarOption('BI', $cancel, true); /* Y or N */

		return $this->_returnMessage();
	}

	function msgCheckin($item, $itmReturnDate, $itmLocation = '', $itmProp = '', $noBlock = 'N', $cancel = '') {
		/* Checkin an item (09) - untested */
		if ($itmLocation == '') {
			/* If no location is specified, assume the defualt location of the SC, behavior suggested by spec*/
			$itmLocation = $this->scLocation;
		}

		$this->_newMessage('09');
		$this->_addFixedOption($noBlock, 1);
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addFixedOption($this->_datestamp($itmReturnDate), 18);
		$this->_addVarOption('AP', $itmLocation);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AB', $item);
		$this->_addVarOption('AC', $this->AC);
		$this->_addVarOption('CH', $itmProp, true);
		$this->_addVarOption('BI', $cancel, true); /* Y or N */

		return $this->_returnMessage();
	}

	function msgBlockPatron($message, $retained = 'N') {
		/* Blocks a patron, and responds with a patron status response  (01) - untested */
		$this->_newMessage('01');
		$this->_addFixedOption($retained, 1); /* Y if card has been retained */
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AL', $message);
		$this->_addVarOption('AA', $this->AA);
		$this->_addVarOption('AC', $this->AC);

		return $this->_returnMessage();
	}

	function msgSCStatus($status = 0, $width = 80, $version = 2) {
		/* self check status message, this should be sent immediately after login  - untested */
		/* status codes, from the spec:
		 * 0 SC unit is OK
		 * 1 SC printer is out of paper
		 * 2 SC is about to shut down
		 */

		if ($version > 3) {
			$version = 2;
		}
		if ($status < 0 || $status > 2) {
			$this->_debugmsg("SIP2: Invalid status passed to msgSCStatus");
			return false;
		}
		$this->_newMessage('99');
		$this->_addFixedOption($status, 1);
		$this->_addFixedOption($width, 3);
		$this->_addFixedOption(sprintf("%03.2f", $version), 4);
		return $this->_returnMessage();
	}

	function msgRequestACSResend() {
		/* Used to request a resend due to CRC mismatch - No sequence number is used */
		$this->_newMessage('97');
		return $this->_returnMessage(false);
	}

	function msgLogin($sipLogin, $sipPassword) {
		/* Login (93) - untested */
		$this->_newMessage('93');
		$this->_addFixedOption($this->UIDalgorithm, 1);
		$this->_addFixedOption($this->PWDalgorithm, 1);
		$this->_addVarOption('CN', $sipLogin);
		$this->_addVarOption('CO', $sipPassword);
		$this->_addVarOption('CP', $this->scLocation, true);
		return $this->_returnMessage();

	}

	function msgPatronInformation($type, $start = '1', $end = '5') {

		/*
		 * According to the specification:
		 * Only one category of items should be  requested at a time, i.e. it would take 6 of these messages,
		 * each with a different position set to Y, to get all the detailed information about a patron's items.
		 */
		$summary['none'] = '      ';
		$summary['hold'] = 'Y     ';
		$summary['overdue'] = ' Y    ';
		$summary['charged'] = '  Y   ';
		$summary['fine'] = '   Y  ';
		$summary['recall'] = '    Y ';
		$summary['unavail'] = '     Y';

		/* Request patron information */
		$this->_newMessage('63');
		$this->_addFixedOption($this->language, 3);
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addFixedOption(sprintf("%-10s", $summary[$type]), 10);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AA', $this->patron);
		$this->_addVarOption('AC', $this->AC, true);
		$this->_addVarOption('AD', $this->patronpwd, true);
		$this->_addVarOption('BP', $start, true); /* old function version used padded 5 digits, not sure why */
		$this->_addVarOption('BQ', $end, true); /* old function version used padded 5 digits, not sure why */
		return $this->_returnMessage();
	}

	function msgEndPatronSession() {
		/*  End Patron Session, should be sent before switching to a new patron. (35) - untested */

		$this->_newMessage('35');
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AA', $this->patron);
		$this->_addVarOption('AC', $this->AC, true);
		$this->_addVarOption('AD', $this->patronpwd, true);
		return $this->_returnMessage();
	}

	/* Fee paid function should go here */
	function msgFeePaid($feeType = '01', $pmtType = '02', $pmtAmount, $curType = 'USD', $feeId = '', $transId = '', $patronId = '') {
		/* Fee payment function (37) - untested */ /* Fee Types: */ /* 01 other/unknown */ /* 02 administrative */ /* 03 damage */ /* 04 overdue */ /* 05 processing */ /* 06 rental*/ /* 07 replacement */ /* 08 computer access charge */ /* 09 hold fee */

		/* Value Payment Type */ /* 00   cash */ /* 01   VISA */
		/* 02   credit card */

		if (!is_numeric($feeType) || $feeType > 99 || $feeType < 1) {
			/* not a valid fee type - exit */
			$this->_debugmsg("SIP2: (msgFeePaid) Invalid fee type: {$feeType}");
			return false;
		}

		if (!is_numeric($pmtType) || $pmtType > 99 || $pmtType < 0) {
			/* not a valid payment type - exit */
			$this->_debugmsg("SIP2: (msgFeePaid) Invalid payment type: {$pmtType}");
			return false;
		}

		$this->_newMessage('37');
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addFixedOption(sprintf('%02d', $feeType), 2);
		$this->_addFixedOption(sprintf('%02d', $pmtType), 2);
		$this->_addFixedOption($curType, 3);
		$this->_addVarOption('BV', $pmtAmount); /* due to currency format localization, it is up to the programmer to properly format their payment amount */
		$this->_addVarOption('AO', $this->AO);
		if (!empty($patronId)) {
			$this->_addVarOption('AA', $patronId);
		} else {
			$this->_addVarOption('AA', $this->patron);
		}
		$this->_addVarOption('AC', $this->AC, true);
//		$this->_addVarOption('AD',$this->patronpwd, true); // Patron password ignored in CarlX 9.6. Leave commented out until an Aspen ILS requires it.
		$this->_addVarOption('CG', $feeId, true);
		$this->_addVarOption('BK', $transId, true);

		return $this->_returnMessage();
	}

	function msgItemInformation($item) {

		$this->_newMessage('17');
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AB', $item);
		$this->_addVarOption('AC', $this->AC, true);
		return $this->_returnMessage();

	}

	function msgItemStatus($item, $itmProp = '') {
		/* Item status update function (19) - untested  */

		$this->_newMessage('19');
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AB', $item);
		$this->_addVarOption('AC', $this->AC, true);
		$this->_addVarOption('CH', $itmProp);
		return $this->_returnMessage();
	}

	function msgPatronEnable() {
		/* Patron Enable function (25) - untested */
		/*  This message can be used by the SC to re-enable canceled patrons. It should only be used for system testing and validation. */
		$this->_newMessage('25');
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AA', $this->patron);
		$this->_addVarOption('AC', $this->AC, true);
		$this->_addVarOption('AD', $this->patronpwd, true);
		return $this->_returnMessage();

	}

	function msgHold($mode, $expDate = '', $holdtype = '', $item = '', $title = '', $fee = 'N', $pkupLocation = '') {
		/* mode validity check */
		/*
		 * - remove hold
		 * + place hold
		 * * modify hold
		 */
		if (strpos('-+*', $mode) === false) {
			/* not a valid mode - exit */
			$this->_debugmsg("SIP2: Invalid hold mode: {$mode}");
			return false;
		}

		if ($holdtype != '' && ($holdtype < 1 || $holdtype > 9)) {
			/*
			 * Valid hold types range from 1 - 9
			 * 1   other
			 * 2   any copy of title
			 * 3   specific copy
			 * 4   any copy at a single branch or location
			 */
			$this->_debugmsg("SIP2: Invalid hold type code: {$holdtype}");
			return false;
		}

		$this->_newMessage('15');
		$this->_addFixedOption($mode, 1);
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->msgBuild .= $this->fldTerminator;
		if ($expDate != '') {
			/* hold expiration date,  due to the use of the datestamp function, we have to check here for empty value. when datestamp is passed an empty value it will generate a current datestamp */
			$this->_addVarOption('BW', $this->_datestamp($expDate), true); /*CarlX Hold spec says this is fixed field, but it behaves like a var field and is optional... */
		}
		$this->_addVarOption('BS', $pkupLocation, true);
		$this->_addVarOption('BY', $holdtype, true);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AA', $this->patron);
		$this->_addVarOption('AD', $this->patronpwd, true);
		$this->_addVarOption('AB', $item, true);
		$this->_addVarOption('AJ', $title, true);
		$this->_addVarOption('AC', $this->AC, true);
		$this->_addVarOption('BO', $fee, true); /* Y when user has agreed to a fee notice */

		return $this->_returnMessage();

	}

	// For CarlX Only
	function msgHoldCarlX($mode, $expDate = '', $holdtype = '', $item = '', $title = '', $fee = null, $pkupLocation = '', $queuePosition = null, $freeze = null, $freezeReactivateDate = null) {
		/* mode validity check */
		/*
		 * - remove hold
		 * + place hold
		 * * modify hold
		 */
		if (strpos('-+*', $mode) === false) {
			/* not a valid mode - exit */
			$this->_debugmsg("SIP2: Invalid hold mode: {$mode}");
			return false;
		}

		if ($holdtype != '' && ($holdtype < 1 || $holdtype > 9)) {
			/*
			 * Valid hold types range from 1 - 9
			 * 1   other
			 * 2   any copy of title
			 * 3   specific copy
			 * 4   any copy at a single branch or location
			 */
			$this->_debugmsg("SIP2: Invalid hold type code: {$holdtype}");
			return false;
		}

		$this->_newMessage('15');
		$this->_addFixedOption($mode, 1);
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->msgBuild .= $this->fldTerminator;
		$this->_addVarOption('AA', $this->patron);
		$this->_addVarOption('AB', $item, true);
		$this->_addVarOption('AC', $this->AC, true);
		$this->_addVarOption('AD', $this->patronpwd, true);
		$this->_addVarOption('AJ', $title, true);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('BO', $fee, true); /* Y when user has agreed to a fee notice */
		$this->_addVarOption('BR', $queuePosition, true);
		$this->_addVarOption('BS', $pkupLocation, true);
		if ($expDate != '') {
			/* hold expiration date,  due to the use of the datestamp function, we have to check here for empty value. when datestamp is passed an empty value it will generate a current datestamp */
			$this->_addVarOption('BW', $this->_datestamp($expDate), true); /*CarlX Hold spec says this is fixed field, but it behaves like a var field and is optional... */
		}
		$this->_addVarOption('BY', $holdtype, true);
		$this->_addVarOption('XG', '', true); // CarlX custom field Issue Identifier // TO DO: Evaluate code changes for Issue level holds
		if ($freeze == 'freeze' && !empty($freezeReactivateDate)) {
			if (substr($freezeReactivateDate, -1) != 'B') {
				$freezeReactivateDate .= 'B';
			}
		}
		if ($freeze == 'thaw') {
			$freezeReactivateDate = date('m/d/Y', $expDate); // CarlX 9.6.4.3 2020 12 31 will set hold cancel date equal to the previous Freeze Not Needed Before date unless a new value is supplied for Not Needed After in the thaw XI field, despite the fact that the same information is provided in BW
		}
		$this->_addVarOption('XI', $freezeReactivateDate, true); // CarlX custom field NNA or NNB Date used with suffix 'B' to indicate Freeze Reactivate Date

		return $this->_returnMessage();

	}

	function msgRenew($item = '', $title = '', $nbDateDue = '', $itmProp = '', $fee = 'N', $noBlock = 'N', $thirdParty = 'N') {
		/* renew a single item (29) - untested */
		$this->_newMessage('29');
		$this->_addFixedOption($thirdParty, 1);
		$this->_addFixedOption($noBlock, 1);
		$this->_addFixedOption($this->_datestamp(), 18);
		if ($nbDateDue != '') {
			/* override default date due */
			$this->_addFixedOption($this->_datestamp($nbDateDue), 18);
		} else {
			/* send a blank date due to allow ACS to use default date due computed for item */
			$this->_addFixedOption('', 18);
		}
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AA', $this->patron);
		$this->_addVarOption('AD', $this->patronpwd, true);
		$this->_addVarOption('AB', $item, true);
		$this->_addVarOption('AJ', $title, true);
		$this->_addVarOption('AC', $this->AC, true);
		$this->_addVarOption('CH', $itmProp, true);
		$this->_addVarOption('BO', $fee, true); /* Y or N */

		return $this->_returnMessage();
	}

	function msgRenewAll($fee = 'N') {
		/* renew all items for a patron (65) - untested */
		$this->_newMessage('65');
		$this->_addFixedOption($this->_datestamp(), 18);
		$this->_addVarOption('AO', $this->AO);
		$this->_addVarOption('AA', $this->patron);
		$this->_addVarOption('AD', $this->patronpwd, true);
		$this->_addVarOption('AC', $this->AC, true);
		$this->_addVarOption('BO', $fee, true); /* Y or N */

		return $this->_returnMessage();
	}

	function parsePatronStatusResponse($response) {
		$result['fixed'] = [
			'PatronStatus' => substr($response, 2, 14),
			'Language' => substr($response, 16, 3),
			'TransactionDate' => substr($response, 19, 18),
		];

		$result['variable'] = $this->_parsevariabledata($response, 37);
		return $result;
	}

	function parseCheckoutResponse($response) {
		$result['fixed'] = [
			'Ok' => substr($response, 2, 1),
			'RenewalOk' => substr($response, 3, 1),
			'Magnetic' => substr($response, 4, 1),
			'Desensitize' => substr($response, 5, 1),
			'TransactionDate' => substr($response, 6, 18),
		];

		$result['variable'] = $this->_parsevariabledata($response, 24);
		return $result;

	}

	function parseCheckinResponse($response) {
		$result['fixed'] = [
			'Ok' => substr($response, 2, 1),
			'Resensitize' => substr($response, 3, 1),
			'Magnetic' => substr($response, 4, 1),
			'Alert' => substr($response, 5, 1),
			'TransactionDate' => substr($response, 6, 18),
		];

		$result['variable'] = $this->_parsevariabledata($response, 24);
		return $result;

	}

	function parseACSStatusResponse($response) {
		$result['fixed'] = [
			'Online' => substr($response, 2, 1),
			'Checkin' => substr($response, 3, 1),
			/* is Checkin by the SC allowed ?*/
			'Checkout' => substr($response, 4, 1),
			/* is Checkout by the SC allowed ?*/
			'Renewal' => substr($response, 5, 1),
			/* renewal allowed? */
			'PatronUpdate' => substr($response, 6, 1),
			/* is patron status updating by the SC allowed ? (status update ok)*/
			'Offline' => substr($response, 7, 1),
			'Timeout' => substr($response, 8, 3),
			'Retries' => substr($response, 11, 3),
			'TransactionDate' => substr($response, 14, 18),
			'Protocol' => substr($response, 32, 4),
		];

		$result['variable'] = $this->_parsevariabledata($response, 36);
		return $result;
	}

	function parseLoginResponse($response) {
		$result['fixed'] = [
			'Ok' => substr($response, 2, 1),
		];
		$result['variable'] = [];
		return $result;
	}

	function parsePatronInfoResponse($response) {

		$result['fixed'] = [
			'PatronStatus' => substr($response, 2, 14),
			'Language' => substr($response, 16, 3),
			'TransactionDate' => substr($response, 19, 18),
			'HoldCount' => intval(substr($response, 37, 4)),
			'OverdueCount' => intval(substr($response, 41, 4)),
			'ChargedCount' => intval(substr($response, 45, 4)),
			'FineCount' => intval(substr($response, 49, 4)),
			'RecallCount' => intval(substr($response, 53, 4)),
			'UnavailableCount' => intval(substr($response, 57, 4)),
		];

		$result['variable'] = $this->_parsevariabledata($response, 61);
		return $result;
	}

	function parseEndSessionResponse($response) {
		/*   Response example:  36Y20080228 145537AOWOHLERS|AAX00000000|AY9AZF474   */

		$result['fixed'] = [
			'EndSession' => substr($response, 2, 1),
			'TransactionDate' => substr($response, 3, 18),
		];


		$result['variable'] = $this->_parsevariabledata($response, 21);

		return $result;
	}

	function parseFeePaidResponse($response) {
		$result['fixed'] = [
			'PaymentAccepted' => substr($response, 2, 1),
			'TransactionDate' => substr($response, 3, 18),
		];

		$result['variable'] = $this->_parsevariabledata($response, 21);
		return $result;

	}

	function parseItemInfoResponse($response) {
		$result['fixed'] = [
			'CirculationStatus' => intval(substr($response, 2, 2)),
			'SecurityMarker' => intval(substr($response, 4, 2)),
			'FeeType' => intval(substr($response, 6, 2)),
			'TransactionDate' => substr($response, 8, 18),
		];

		$result['variable'] = $this->_parsevariabledata($response, 26);

		return $result;
	}

	function parseItemStatusResponse($response) {
		$result['fixed'] = [
			'PropertiesOk' => substr($response, 2, 1),
			'TransactionDate' => substr($response, 3, 18),
		];

		$result['variable'] = $this->_parsevariabledata($response, 21);
		return $result;

	}

	function parsePatronEnableResponse($response) {
		$result['fixed'] = [
			'PatronStatus' => substr($response, 2, 14),
			'Language' => substr($response, 16, 3),
			'TransactionDate' => substr($response, 19, 18),
		];

		$result['variable'] = $this->_parsevariabledata($response, 37);
		return $result;

	}

	function parseHoldResponse($response) {

		$result['fixed'] = [
			'Ok' => substr($response, 2, 1),
			'available' => substr($response, 3, 1),
			'TransactionDate' => substr($response, 4, 18),
			'ExpirationDate' => substr($response, 22, 18),
		];


		$result['variable'] = $this->_parsevariabledata($response, 40);

		return $result;
	}


	function parseRenewResponse($response) {
		/* Response Example:  300NUU20080228    222232AOWOHLERS|AAX00000241|ABM02400028262|AJFolksongs of Britain and Ireland|AH5/23/2008,23:59|CH|AFOverride required to exceed renewal limit.|AY1AZCDA5 */
		$result['fixed'] = [
			'Ok' => substr($response, 2, 1),
			'RenewalOk' => substr($response, 3, 1),
			'Magnetic' => substr($response, 4, 1),
			'Desensitize' => substr($response, 5, 1),
			'TransactionDate' => substr($response, 6, 18),
		];


		$result['variable'] = $this->_parsevariabledata($response, 24);

		return $result;
	}

	function parseRenewAllResponse($response) {
		$result['fixed'] = [
			'Ok' => substr($response, 2, 1),
			'Renewed' => substr($response, 3, 4),
			'NotRenewed' => substr($response, 7, 4),
			'TransactionDate' => substr($response, 11, 18),
		];


		$result['variable'] = $this->_parsevariabledata($response, 29);

		return $result;
	}


	function get_message($message) {
		/* sends the current message, and gets the response */
		$result = '';
		$terminator = '';
		$nr = '';

		$this->_debugmsg('SIP2: Sending SIP2 request...');
		$this->_debugmsg($message);
		socket_write($this->socket, $message, strlen($message));
		$this->Sleep();

		$this->_debugmsg('SIP2: Request Sent, Reading response');

		//Read from the socket one byte at a time until we read a carriage return
		//Or until the connection receives an error ($nr === false).
		while ($terminator != "\x0D" && $nr !== FALSE) {
			$nr = socket_recv($this->socket, $terminator, 1, 0);
			$result = $result . $terminator;
		}
		if ($nr === false) {
			//Whoops, we got an error
			global $logger;
			$lastError = socket_last_error($this->socket);
			$logger->log("Error reading data from socket ($lastError) " . socket_strerror($lastError), Logger::LOG_ERROR);
		}

		$this->_debugmsg("SIP2: {$result}");

		/* test message for CRC validity */
		if ($this->_check_crc($result)) {
			/* reset the retry counter on successful send */
			$this->retry = 0;
			$this->_debugmsg("SIP2: Message from ACS passed CRC check");
		} else {
			/* CRC check failed, request a resend */
			$this->retry++;
			if ($this->retry < $this->maxretry) {
				/* try again */
				$this->_debugmsg("SIP2: Message failed CRC check, retrying ({$this->retry})");

				$result = $this->get_message($message);
			} else {
				/* give up */
				$this->_debugmsg("SIP2: Failed to get valid CRC after {$this->maxretry} retries.");
				return false;
			}
		}
		//remove extraneous characters - typically carriage returns and line feeds from the beginning and end.
		return trim($result);
	}

	function connect($sipLogin = null, $sipPassword = null) {
		global $logger;
		/* Socket Communications  */
		$this->_debugmsg("SIP2: --- BEGIN SIP communication ---");

		/* Get the IP address for the target host. */
		$address = gethostbyname($this->hostname);

		/* Create a TCP/IP socket. */
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		/* check for actual truly false result using ===*/
		if ($this->socket === false) {
			$logger->log("Unable to create socket to SIP server at $this->hostname", Logger::LOG_ERROR);
			$this->_debugmsg("SIP2: socket_create() failed: reason: " . socket_strerror($this->socket));
			return false;
		} else {
			$this->_debugmsg("SIP2: Socket Created");
		}
		$this->_debugmsg("SIP2: Attempting to connect to '$address' on port '{$this->port}'...");

		//Set SIP timeouts
		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
			'sec' => 2,
			'usec' => 500,
		]);
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, [
			'sec' => 2,
			'usec' => 500,
		]);
		//Make the socket blocking so we can ensure we get responses without rewriting everything.
		socket_set_block($this->socket);

		/* open a connection to the host */
		$connectionTimeout = 10;
		$connectStart = time();
		while (!@socket_connect($this->socket, $address, $this->port)) {
			$error = socket_last_error($this->socket);
			if ($error == 114 || $error == 115) {
				if ((time() - $connectStart) >= $connectionTimeout) {
					socket_close($this->socket);
					$logger->log("Connection to $address $this->port timed out", Logger::LOG_ERROR);
					return false;
				}
				$logger->log("Waiting for connection", Logger::LOG_DEBUG);
				sleep(1);
				continue;
			} else {
				$logger->log("Unable to connect to $address $this->port", Logger::LOG_ERROR);
				$logger->log("SIP2: socket_connect() failed.\nReason: ($error) " . socket_strerror($error), Logger::LOG_ERROR);
				$this->_debugmsg("SIP2: socket_connect() failed.\nReason: ($error) " . socket_strerror($error));
				return false;
			}
		}

		$this->_debugmsg("SIP2: --- SOCKET READY ---");

		if (!empty($sipLogin) && !empty($sipPassword)) {
			$loginMessage = $this->msgLogin($sipLogin, $sipPassword);
			$loginResult = $this->get_message($loginMessage);
			$loginResponse = $this->parseLoginResponse($loginResult);

			if ($loginResponse['fixed']['Ok'] == 1) {
				$logger->log("Logged into SIP client with SIP credentials", Logger::LOG_DEBUG);
				$this->_debugmsg("SIP2: --- LOGIN TO SIP SUCCEEDED ---");
			} else {
				return false;
			}

			//TODO: If a partner ever needs Telnet login, this may need to be reinstituted.
//			$lineEnding = "\r\n";
//
//			//Send login
//			//Read the login prompt
//			$prompt = $this->getResponse();
//			$logger->log("Login Prompt Received was " . $prompt, Logger::LOG_DEBUG);
//			$login = $sipLogin;
//            /** @noinspection PhpUnusedLocalVariableInspection */
//			$ret = socket_write($this->socket, $login, strlen($login));
//			$ret = socket_write($this->socket, $lineEnding, strlen($lineEnding));
//			$logger->log("Wrote $ret bytes for login", Logger::LOG_DEBUG);
//			$this->Sleep();
//
//			$prompt = $this->getResponse();
//			$logger->log("Password Prompt Received was " . $prompt, Logger::LOG_DEBUG);
//			$password = $sipPassword;
//            /** @noinspection PhpUnusedLocalVariableInspection */
//            $ret = socket_write($this->socket, $password, strlen($password));
//			$ret = socket_write($this->socket, $lineEnding, strlen($lineEnding));
//			$logger->log("Wrote $ret bytes for password", Logger::LOG_DEBUG);
//
//			if ($this->use_usleep){
//				usleep($this->loginsleeptime);
//			}else{
//				sleep(1);
//			}

//			//Wait for a response
//			$initialLoginResponse = $this->getResponse();
//			$logger->log("Login response is " . $initialLoginResponse, Logger::LOG_DEBUG);
//			$this->Sleep();
//
//			//$loginData = $this->parseLoginResponse($loginResponse);
//			if (strpos($initialLoginResponse, 'Login OK.  Initiating SIP') === 0){
//				$logger->log("Logged into SIP client with telnet credentials", Logger::LOG_DEBUG);
//				$this->_debugmsg( "SIP2: --- LOGIN TO SIP SUCCEEDED ---" );
//			}else{
//				$logger->log("Unable to login to SIP server using telnet credentials", Logger::LOG_ERROR);
//				$this->_debugmsg( "SIP2: --- LOGIN TO SIP FAILED ---" );
//				$this->_debugmsg( $initialLoginResponse);
//				return false;
//			}
		}
		/* return the result from the socket connect */
		return true;

	}

	function getResponse() {
		$buffer = '';
		/** @noinspection PhpUnusedLocalVariableInspection */
		$bytesRead = socket_recv($this->socket, $buffer, 2048, MSG_WAITALL);
		return $buffer;
	}

	function disconnect() {
		/*  Close the socket */
		socket_close($this->socket);
	}

	/* Core local utility functions */
	function _datestamp($timestamp = '') {
		/* generate a SIP2 compatable datestamp */
		/* From the spec:
		 * YYYYMMDDZZZZHHMMSS.
		 * All dates and times are expressed according to the ANSI standard X3.30 for date and X3.43 for time.
		 * The ZZZZ field should contain blanks (code $20) to represent local time. To represent universal time,
		 *  a Z character(code $5A) should be put in the last (right hand) position of the ZZZZ field.
		 * To represent other time zones the appropriate character should be used; a Q character (code $51)
		 * should be put in the last (right hand) position of the ZZZZ field to represent Atlantic Standard Time.
		 * When possible local time is the preferred format.
		 */
		if ($timestamp != '') {
			/* Generate a proper date time from the date provided */
			return date('Ymd    His', $timestamp);
		} else {
			/* Current Date/Time */
			return date('Ymd    His');
		}
	}

	function _parsevariabledata($response, $start) {

		$result = [];
		$result['Raw'] = explode("|", substr($response, $start, -7));
		foreach ($result['Raw'] as $item) {
			$field = substr($item, 0, 2);
			$value = substr($item, 2);
			/* SD returns some odd values on ocassion, Unable to locate the purpose in spec, so I strip from
			 * the parsed array. Orig values will remain in ['raw'] element
			 */
			$clean = trim($value, "\x00..\x1F");
			if (trim($clean) <> '') {
				$result[$field][] = $clean;
			}
		}
		$result['AZ'][] = substr($response, -5);

		return ($result);
	}

	function _crc($buf) {
		/* Calculate CRC  */
		$sum = 0;

		$len = strlen($buf);
		for ($n = 0; $n < $len; $n++) {
			$sum = $sum + ord(substr($buf, $n, 1));
		}

		$crc = ($sum & 0xFFFF) * -1;

		/* 2008.03.15 - Fixed a bug that allowed the checksum to be larger then 4 digits */
		return substr(sprintf("%4X", $crc), -4, 4);
	} /* end crc */

	function _getseqnum() {
		/* Get a sequence number for the AY field */
		/* valid numbers range 0-9 */
		$this->seq++;
		if ($this->seq > 9) {
			$this->seq = 0;
		}
		return ($this->seq);
	}

	function _debugmsg($message) {
		/* custom debug function,  why repeat the check for the debug flag in code... */
		if ($this->debug) {
			global $logger;
			$logger->log($message, Logger::LOG_ERROR);
			//echo($message . "<br/>\r\n");
		}
	}

	function _check_crc($message) {
		/* test the received message's CRC by generating our own CRC from the message */
		$test = preg_split('/(.{4})$/', trim($message), 2, PREG_SPLIT_DELIM_CAPTURE);

		if (isset($test[1]) && $this->_crc($test[0]) == $test[1]) {
			return true;
		} else {
			return false;
		}
	}

	function _newMessage($code) {
		/* resets the msgBuild variable to the value of $code, and clears the flag for fixed messages */
		$this->noFixed = false;
		$this->msgBuild = $code;
	}

	function _addFixedOption($value, $len) {
		/* adds afixed length option to the msgBuild IF no variable options have been added. */
		if ($this->noFixed) {
			return false;
		} else {
			$this->msgBuild .= sprintf("%{$len}s", substr($value, 0, $len));
			return true;
		}
	}

	function _addVarOption($field, $value, $optional = false) {
		/* adds a varaiable length option to the message, and also prevents adding addtional fixed fields */
		if ($optional == true && $value == '') {
			/* skipped */
			$this->_debugmsg("SIP2: Skipping optional field {$field}");
		} else {
			$this->noFixed = true; /* no more fixed for this message */
			$this->msgBuild .= $field . substr($value, 0, 255) . $this->fldTerminator;
		}
		return true;
	}

	function _returnMessage($withSeq = true, $withCrc = true) {
		/* Finalizes the message and returns it.  Message will remain in msgBuild until newMessage is called */
		if ($withSeq) {
			$this->msgBuild .= 'AY' . $this->_getseqnum();
		}
		if ($withCrc) {
			$this->msgBuild .= 'AZ';
			$this->msgBuild .= $this->_crc($this->msgBuild);
		}
		$this->msgBuild .= $this->msgTerminator;

		return $this->msgBuild;
	}

	function Sleep() {
		if ($this->use_usleep) {
			usleep($this->sleeptime);
		} else {
			sleep(1);
		}
	}
}
