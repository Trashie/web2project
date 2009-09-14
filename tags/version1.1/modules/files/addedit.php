<?php /* $Id$ $URL$ */
if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}

$folder = intval(w2PgetParam($_GET, 'folder', 0));
$file_id = intval(w2PgetParam($_GET, 'file_id', 0));
$ci = w2PgetParam($_GET, 'ci', 0) == 1 ? true : false;
$preserve = $w2Pconfig['files_ci_preserve_attr'];

// check permissions for this record
$perms = &$AppUI->acl();
$canAuthor = $perms->checkModule('files', 'add');
$canEdit = $perms->checkModuleItem('files', 'edit', $file_id);

// check permissions
if (!$canAuthor && !$file_id) {
	$AppUI->redirect('m=public&a=access_denied');
}

if (!$canEdit && $file_id) {
	$AppUI->redirect('m=public&a=access_denied');
}

if (file_exists(W2P_BASE_DIR . '/modules/helpdesk/config.php')) {
	include (W2P_BASE_DIR . '/modules/helpdesk/config.php');
}
$canAdmin = $perms->checkModule('system', 'edit');
// add to allow for returning to other modules besides Files
$referrerArray = parse_url($_SERVER['HTTP_REFERER']);
$referrer = $referrerArray['query'];

// load the companies class to retrieved denied companies
require_once ($AppUI->getModuleClass('companies'));
require_once ($AppUI->getModuleClass('projects'));
require_once $AppUI->getModuleClass('tasks');

$file_task = intval(w2PgetParam($_GET, 'file_task', 0));
$file_parent = intval(w2PgetParam($_GET, 'file_parent', 0));
$file_project = intval(w2PgetParam($_GET, 'project_id', 0));
$file_helpdesk_item = intval(w2PgetParam($_GET, 'file_helpdesk_item', 0));

$q = &new DBQuery;

// check if this record has dependencies to prevent deletion
$msg = '';
$obj = new CFile();
$canDelete = $obj->canDelete($msg, $file_id);

// load the record data
// $obj = null;
if ($file_id > 0 && !$obj->load($file_id)) {
	$AppUI->setMsg('File');
	$AppUI->setMsg('invalidID', UI_MSG_ERROR, true);
	$AppUI->redirect();
}
if ($file_id > 0) {
	// Check to see if the task or the project is also allowed.
	if ($obj->file_task) {
		if (!$perms->checkModuleItem('tasks', 'view', $obj->file_task)) {
			$AppUI->redirect('m=public&a=access_denied');
		}
	}
	if ($obj->file_project) {
		if (!$perms->checkModuleItem('projects', 'view', $obj->file_project)) {
			$AppUI->redirect('m=public&a=access_denied');
		}
	}
}

if ($obj->file_checkout != $AppUI->user_id) {
	$ci = false;
}

if (!$canAdmin)
	$canAdmin = $obj->canAdmin();

if ($obj->file_checkout == 'final' && !$canAdmin) {
	$AppUI->redirect('m=public&a=access_denied');
}
// setup the title block
$ttl = $file_id ? 'Edit File' : 'Add File';
$ttl = $ci ? 'Checking in' : $ttl;
$titleBlock = new CTitleBlock($ttl, 'folder5.png', $m, $m . '.' . $a);
$titleBlock->addCrumb('?m=files', 'files list');
if ($canDelete && $file_id > 0 && !$ci) {
	$titleBlock->addCrumbDelete('delete file', $canDelete, $msg);
}
$titleBlock->show();

//Clear the file id if checking out so a new version is created.
if ($ci) {
	$file_id = 0;
}

if ($obj->file_project) {
	$file_project = $obj->file_project;
}
if ($obj->file_task) {
	$file_task = $obj->file_task;
	$task_name = $obj->getTaskName();
} else
	if ($file_task) {
		$task = new CTask();
		$task->load($file_task);
		$task_name = $task->task_name;
	} else {
		$task_name = '';
	}
	if (isset($obj->file_helpdesk_item)) {
		$file_helpdesk_item = $obj->file_helpdesk_item;
	}

$extra = array('where' => 'project_active = 1');
$project = new CProject();
$projects = $project->getAllowedRecords($AppUI->user_id, 'projects.project_id,project_name', 'project_name', null, $extra, 'projects');
$projects = arrayMerge(array('0' => $AppUI->_('None', UI_OUTPUT_RAW)), $projects);

$folders = getFolderSelectList();
?>
<script language="javascript">
function submitIt() {
	var f = document.uploadFrm;
	f.submit();
}
function cancelIt() {
	var f = document.uploadFrm;
	f.cancel.value='1';
	f.submit();
}
function delIt() {
	if (confirm( '<?php echo $AppUI->_('filesDelete', UI_OUTPUT_JS); ?>' )) {
		var f = document.uploadFrm;
		f.del.value='1';
		f.submit();
	}
}
function popTask() {
	var f = document.uploadFrm;
	if (f.file_project.selectedIndex == 0) {
		alert( '<?php echo $AppUI->_('Please select a project first!', UI_OUTPUT_JS); ?>' );
	} else {
		window.open('./index.php?m=public&a=selector&dialog=1&callback=setTask&table=tasks&task_project=' + f.file_project.options[f.file_project.selectedIndex].value, 'task','left=50,top=50,height=250,width=400,resizable')
	}
}

function finalCI() {
	var f = document.uploadFrm;
	if (f.final_ci.value == '1') {
		f.file_checkout.value = 'final';
		f.file_co_reason.value = 'Final Version';
	} else {
		f.file_checkout.value = '';
		f.file_co_reason.value = '';
	}
}

// Callback function for the generic selector
function setTask( key, val ) {
	var f = document.uploadFrm;
	if (val != '') {
		f.file_task.value = key;
		f.task_name.value = val;
	} else {
		f.file_task.value = '0';
		f.task_name.value = '';
	}
}
</script>

<form name="uploadFrm" action="?m=files" enctype="multipart/form-data" method="post">
	<input type="hidden" name="dosql" value="do_file_aed" />
	<input type="hidden" name="del" value="0" />
	<input type="hidden" name="cancel" value="0" />
	<input type="hidden" name="file_id" value="<?php echo $obj->file_id; ?>" />
	<input type="hidden" name="file_version_id" value="<?php echo $obj->file_version_id; ?>" />
	<input type="hidden" name="redirect" value="<?php echo $referrer; ?>" />
	<input type="hidden" name="file_helpdesk_item" value="<?php echo $file_helpdesk_item; ?>" />
	<table width="100%" border="0" cellpadding="3" cellspacing="3" class="std">
		<tr>
			<td width="80%" valign="top" align="center">
				<table cellspacing="1" cellpadding="2" width="60%">
					<tr>
						<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Folder'); ?>:</td>
						<td align="left">
							<?php if ($file_id == 0 && !$ci) { ?>
								<?php echo arraySelectTree($folders, 'file_folder', 'style="width:175px;" class="text"', ($file_helpdesk_item ? getHelpdeskFolder() : $folder)); ?>
							<?php } else { ?>
								<?php echo arraySelectTree($folders, 'file_folder', 'style="width:175px;" class="text"', ($file_helpdesk_item ? getHelpdeskFolder() : $obj->file_folder)); ?>
							<?php } ?>
						</td>
					</tr>		
					<?php if ($obj->file_id) { ?>
						<tr>
							<td align="right" nowrap="nowrap"><?php echo $AppUI->_('File Name'); ?>:</td>
							<td align="left" class="hilite"><?php echo strlen($obj->file_name) == 0 ? 'n/a' : $obj->file_name; ?></td>
							<td>
								<a href="./fileviewer.php?file_id=<?php echo $obj->file_id; ?>"><?php echo $AppUI->_('download'); ?></a>
							</td>
						</tr>
						<tr valign="top">
							<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Type'); ?>:</td>
							<td align="left" class="hilite"><?php echo $obj->file_type; ?></td>
						</tr>
						<tr>
							<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Size'); ?>:</td>
							<td align="left" class="hilite"><?php echo $obj->file_size; ?></td>
						</tr>
						<tr>
							<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Uploaded By'); ?>:</td>
							<td align="left" class="hilite"><?php echo $obj->getOwner(); ?></td>
						</tr>
					<?php } ?>
					<?php echo file_show_attr(); ?>
					<tr>
						<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Description'); ?>:</td>
						<td align="left">
							<textarea name="file_description" class="textarea" rows="4" style="width:270px"><?php echo $obj->file_description; ?></textarea>
						</td>
					</tr>
					<tr>
						<td align="right" nowrap="nowrap"><?php echo $AppUI->_('Upload File'); ?>:</td>
						<td align="left"><input type="File" name="formfile" style="width:270px" /></td>
					</tr>
					<?php if ($ci || ($canAdmin && $obj->file_checkout == 'final')) { ?>
						<tr>
							<td align="right" nowrap="nowrap">&nbsp;</td>
							<td align="left"><input type="checkbox" name="final_ci" id="final_ci" onclick="finalCI()" /><label for="final_ci"><?php echo $AppUI->_('Final Version'); ?></label></td>		
						</tr>
					<?php } ?>
					<tr>
						<td align="right" nowrap="nowrap">&nbsp;</td>
						<td align="left"><input type="checkbox" name="notify" id="notify" checked="checked" /><label for="notify"><?php echo $AppUI->_('Notify Assignees of Task or Project Owner by Email'); ?></label></td>		
					</tr>
				</table>
			</td>
			<td valign="top" align="right">
				<?php
				if ($obj->file_id && $obj->file_checkout <> '' && ((int) $obj->file_checkout == $AppUI->user_id || $canAdmin)) {
					?><input type="button" class="button" value="<?php echo $AppUI->_('cancel checkout'); ?>" onclick="cancelIt()" /><?php
				}
				?>
			</td>
		</tr>
		<tr>
			<td>
				<input class="button" type="button" name="cancel" value="<?php echo $AppUI->_('cancel'); ?>" onclick="javascript:if(confirm('<?php echo $AppUI->_('Are you sure you want to cancel?', UI_OUTPUT_JS); ?>')){location.href = '?<?php echo $AppUI->getPlace(); ?>'; }" />
			</td>
			<td align="right">
				<?php
				if (substr(sprintf('%o', fileperms(W2P_BASE_DIR.'/files')), -4) == '0777') {
					?><input type="button" class="button" value="<?php echo $AppUI->_('submit'); ?>" onclick="submitIt()" /><?php
				} else {
					?><span class="error">File uploads not allowed. Please check permissions on the /files directory.</span><?php
				}
				?>
			</td>
		</tr>
	</table>
</form>