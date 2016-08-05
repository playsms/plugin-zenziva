<?php
defined('_SECURE_') or die('Forbidden');

$data = registry_search(0, 'gateway', 'zenziva');
$plugin_config['zenziva'] = $data['gateway']['zenziva'];
$plugin_config['zenziva']['name'] = 'zenziva';
$plugin_config['zenziva']['default_url'] = 'http://your_subdomain.zenziva.com';
if (!trim($plugin_config['zenziva']['url'])) {
	$plugin_config['zenziva']['url'] = $plugin_config['zenziva']['default_url'];
}

// smsc configuration
$plugin_config['zenziva']['_smsc_config_'] = array(
	'url' => _('Zenziva URL'),
	'userkey' => _('Userkey'),
	'passkey' => _('Passkey'),
	/* 'module_sender' => _('Module sender ID'), */
	'datetime_timezone' => _('Module timezone') 
);
