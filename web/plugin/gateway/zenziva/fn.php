<?php

/**
 * This file is part of playSMS.
 *
 * playSMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * playSMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_SECURE_') or die('Forbidden');

// hook_sendsms
// called by main sms sender
// return true for success delivery
// $smsc : smsc
// $sms_sender : sender mobile number
// $sms_footer : sender sms footer or sms sender ID
// $sms_to : destination sms number
// $sms_msg : sms message tobe delivered
// $gpid : group phonebook id (optional)
// $uid : sender User ID
// $smslog_id : sms ID
function zenziva_hook_sendsms($smsc, $sms_sender, $sms_footer, $sms_to, $sms_msg, $uid = '', $gpid = 0, $smslog_id = 0, $sms_type = 'text', $unicode = 0) {
	global $plugin_config;
	
	_log("enter smsc:" . $smsc . " smslog_id:" . $smslog_id . " uid:" . $uid . " to:" . $sms_to, 3, "zenziva_hook_sendsms");
	
	// override plugin gateway configuration by smsc configuration
	$plugin_config = gateway_apply_smsc_config($smsc, $plugin_config);
	
	$sms_sender = stripslashes($sms_sender);
	if ($plugin_config['zenziva']['module_sender']) {
		$sms_sender = $plugin_config['zenziva']['module_sender'];
	}
	
	$sms_footer = stripslashes($sms_footer);
	$sms_msg = stripslashes($sms_msg);
	$ok = false;
	
	if ($sms_footer) {
		$sms_msg = $sms_msg . $sms_footer;
	}
	
	// no sender config yet	
	//if ($sms_sender && $sms_to && $sms_msg) {
	if ($sms_to && $sms_msg) {
		
		$unicode_query_string = '';
		if ($unicode) {
			if (function_exists('mb_convert_encoding')) {
				// $sms_msg = mb_convert_encoding($sms_msg, "UCS-2BE", "auto");
				$sms_msg = mb_convert_encoding($sms_msg, "UCS-2", "auto");
				// $sms_msg = mb_convert_encoding($sms_msg, "UTF-8", "auto");
			}
		}
		
		// http://your_subdomain.zenziva.com/apps/smsapi.php?userkey=your_userkey_here&passkey=your_passkey_here&nohp=0123456789&tipe=reguler&pesan=your+message
		$url = $plugin_config['zenziva']['url'] . "/apps/smsapi.php?";
		$url .= "userkey=" . $plugin_config['zenziva']['userkey'];
		$url .= "&passkey=" . $plugin_config['zenziva']['passkey'];
		$url .= "&nohp=" . urlencode($sms_to);
		$url .= "&pesan=" . urlencode($sms_msg);
		$url .= "&tipe=reguler";
		$url = trim($url);
		
		_log("send url:[" . $url . "]", 3, "zenziva_hook_sendsms");
		
		// send it
		$response = file_get_contents($url);
		
		/*
		 * <?xml version="1.0" encoding="UTF-8"?>
		 * <response>
		 * <message>
		 * <messageId>123456</messageId>
		 * <to>0123456789</to>
		 * <status>0</status>
		 * <text>Success</text>
		 * </message>
		 * </response>
		 */
		
		if ($response) {
			$resp = core_xml_to_array($response);
			if (is_array($resp['message'])) {
				$c_message_id = (int) $resp['message']['messageId'];
				$c_status_id = (int) $resp['message']['status'];
				$c_status_text = $resp['message']['text'];
			}
		}
		
		// a single non-zero respond will be considered as a SENT response
		if ($c_message_id) {
			_log("sent smslog_id:" . $smslog_id . " message_id:" . $c_message_id . " status_id:" . $c_status_id . " status_text:[" . $c_status_text . "] smsc:" . $smsc, 2, "zenziva_hook_sendsms");
			$db_query = "
				INSERT INTO " . _DB_PREF_ . "_gatewayZenziva_log (local_smslog_id, remote_smslog_id)
				VALUES ('$smslog_id', '$c_message_id')";
			$id = @dba_insert_id($db_query);
			if ($id) {
				$ok = true;
				$p_status = 1;
				dlr($smslog_id, $uid, $p_status);
			}
		} else if ($c_status_id) {
			_log("failed smslog_id:" . $smslog_id . " message_id:" . $c_message_id . " status_id:" . $c_status_id . " status_text:[" . $c_status_text . "] smsc:" . $smsc, 2, "zenziva_hook_sendsms");
		} else {
			$resp = $response;
			_log("invalid smslog_id:" . $smslog_id . " resp:[" . $resp . "] smsc:" . $smsc, 2, "zenziva_hook_sendsms");
		}
	}
	if (!$ok) {
		$p_status = 2;
		dlr($smslog_id, $uid, $p_status);
	}
	
	return $ok;
}

function zenziva_hook_playsmsd() {
	if (!core_playsmsd_timer(60)) {
		return;
	}
	
	global $plugin_config;
	
	$smscs = gateway_getall_smsc_names('zenziva');
	
	foreach ($smscs as $smsc) {
		
		// override plugin gateway configuration by smsc configuration
		$plugin_config = gateway_apply_smsc_config($smsc, $plugin_config);
		
		// http://your_subdomain.zenziva.com/api/inboxgetall.php?userkey=your_userkey_here&passkey=your_passkey_here&status=unread
		$url = $plugin_config['zenziva']['url'] . "/api/inboxgetall.php?";
		$url .= "userkey=" . $plugin_config['zenziva']['userkey'];
		$url .= "&passkey=" . $plugin_config['zenziva']['passkey'];
		$url .= "&status=unread";
		$url = trim($url);
		
		//_log("fetch url:[" . $url . "]", 3, "zenziva_hook_playsmsd");
		

		// fetch it
		$response = file_get_contents($url);
		
		/*
		 * <?xml version="1.0" encoding="UTF-8"?>
		 * <response>
		 * <message>
		 * <id>1</id>
		 * <tgl>2016-08-04</tgl>
		 * <waktu>12:37:21</waktu>
		 * <isiPesan> Test ok</isiPesan>
		 * <dari>+6281297798358</dari>
		 * </message>
		 * </response>
		 */
		
		if ($response) {
			$resp = core_xml_to_array($response);
			if (is_array($resp['message'])) {
				
				$inbox_id = (int) $resp['message']['id'];
				$sms_datetime = core_display_datetime(core_get_datetime());
				$sms_sender = addslashes($resp['message']['dari']);
				$message = addslashes($resp['message']['isiPesan']);
				$sms_receiver = '';
				$smsc = '';
				
				if ($inbox_id && $sms_sender && $message) {
					_log("received inbox_id:[" . $inbox_id . "]  dt:[" . core_display_datetime($sms_datetime) . "] s:[" . $sms_sender . "] m:[" . $message . "]", 2, "zenziva_hook_playsmsd");
					
					recvsms($sms_datetime, $sms_sender, $message, $sms_receiver, $smsc);
				}
			}
		}
	}
}
