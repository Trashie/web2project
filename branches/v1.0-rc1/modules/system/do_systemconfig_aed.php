<?php /* $Id$ $URL$ */
if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}

$perms = &$AppUI->acl();
if (!$perms->checkModule('system', 'edit')) {
	$AppUI->redirect('m=public&a=access_denied');
}

$obj = new CConfig();

// set all checkboxes to false
// overwrite the true/enabled/checked checkboxes later
$q = new DBQuery;
$q->addTable('config');
$q->addUpdate('config_value', 'false');
$q->addWhere('config_type = \'checkbox\'');
$rs = $q->loadResult();
$q->clear();

foreach ($_POST['w2Pcfg'] as $name => $value) {
	$obj->config_name = $name;
	$obj->config_value = $value;

	// grab the appropriate id for the object in order to ensure
	// that the db is updated well (config_name must be unique)
	$obj->config_id = $_POST['w2PcfgId'][$name];

	// prepare (and translate) the module name ready for the suffix
	$AppUI->setMsg('System Configuration');
	if (($msg = $obj->store())) {
		$AppUI->setMsg($msg, UI_MSG_ERROR);
	} else {
		$AppUI->setMsg('updated', UI_MSG_OK, true);
	}
}
$AppUI->redirect();
?>