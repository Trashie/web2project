<?php /* $Id$ $URL$ */
if (!defined('W2P_BASE_DIR')) {
	die('You should not access this file directly.');
}

/**
 *	@package web2Project
 *	@subpackage modules
 *	@version $Revision$
 */
require_once ($AppUI->getSystemClass('libmail'));
require_once ($AppUI->getSystemClass('w2p'));
require_once ($AppUI->getLibraryClass('PEAR/Date'));
require_once ($AppUI->getModuleClass('tasks'));
require_once ($AppUI->getModuleClass('companies'));
require_once ($AppUI->getModuleClass('departments'));
require_once ($AppUI->getModuleClass('files'));

// project statii
$pstatus = w2PgetSysVal('ProjectStatus');
$ptype = w2PgetSysVal('ProjectType');

$ppriority_name = w2PgetSysVal('ProjectPriority');
$ppriority_color = w2PgetSysVal('ProjectPriorityColor');

$priority = array();
foreach ($ppriority_name as $key => $val) {
	$priority[$key]['name'] = $val;
}
foreach ($ppriority_color as $key => $val) {
	$priority[$key]['color'] = $val;
}

/*
// kept for reference
$priority = array(
-1 => array(
'name' => 'low',
'color' => '#E5F7FF'
),
0 => array(
'name' => 'normal',
'color' => ''//#CCFFCA
),
1 => array(
'name' => 'high',
'color' => '#FFDCB3'
),
2 => array(
'name' => 'immediate',
'color' => '#FF887C'
)
);
*/

/**
 * The Project Class
 */
class CProject extends CW2pObject {
	public $project_id = null;
	public $project_company = null;
	public $project_department = null;
	public $project_name = null;
	public $project_short_name = null;
	public $project_owner = null;
	public $project_url = null;
	public $project_demo_url = null;
	public $project_start_date = null;
	public $project_end_date = null;
	public $project_actual_end_date = null;
	public $project_status = null;
	public $project_percent_complete = null;
	public $project_color_identifier = null;
	public $project_description = null;
	public $project_target_budget = null;
	public $project_actual_budget = null;  
  public $project_scheduled_hours = null;
  public $project_worked_hours = null;
  public $project_task_count = null;
	public $project_creator = null;
	public $project_active = null;
	public $project_private = null;
	public $project_departments = null;
	public $project_contacts = null;
	public $project_priority = null;
	public $project_type = null;
	public $project_parent = null;
	public $project_original_parent = null;
	public $project_location = '';

	public function CProject() {
		$this->CW2pObject('projects', 'project_id');
	}

	public function check() {
		$errorArray = array();
    $baseErrorMsg = get_class($this) . '::store-check failed - ';

    $this->project_id = intval($this->project_id);

		if ('' == $this->project_name) {
			$errorArray['project_name'] = $baseErrorMsg . 'project name is NULL';
		}
    if ('' == $this->project_short_name) {
      $errorArray['project_short_name'] = $baseErrorMsg . 'project short name is NULL';
    }
    if ((int) $this->project_company == 0) {
    	$errorArray['project_company'] = $baseErrorMsg . 'project company is NULL';
    }
    if ('' == $this->project_priority) {
    	$errorArray['project_priority'] = $baseErrorMsg . 'project priority is NULL';
    }
    if ('' == $this->project_color_identifier) {
      $errorArray['project_color_identifier'] = $baseErrorMsg . 'project color identifier is NULL';
    }
    if ('' == $this->project_type) {
    	$errorArray['project_type'] = $baseErrorMsg . 'project type is NULL';
    }
    if ('' == $this->project_status) {
      $errorArray['project_status'] = $baseErrorMsg . 'project status is NULL';
    }

		// ensure changes of state in checkboxes is captured
		$this->project_active = intval($this->project_active);
		$this->project_private = intval($this->project_private);

		$this->project_target_budget = $this->project_target_budget ? $this->project_target_budget : 0.00;
		$this->project_actual_budget = $this->project_actual_budget ? $this->project_actual_budget : 0.00;

		// Make sure project_short_name is the right size (issue for languages with encoded characters)
		if (strlen($this->project_short_name) > 10) {
			$this->project_short_name = substr($this->project_short_name, 0, 10);
		}
		if (empty($this->project_end_date)) {
			$this->project_end_date = null;
		}
		return $errorArray;
	}

	public function load($oid = null, $strip = true) {
		$result = parent::load($oid, $strip);
		if ($result && $oid) {
			$working_hours = (w2PgetConfig('daily_working_hours') ? w2PgetConfig('daily_working_hours') : 8);

			$q = new DBQuery;
			$q->addTable('projects');
			$q->addQuery('SUM(t1.task_duration * t1.task_percent_complete * IF(t1.task_duration_type = 24, ' . $working_hours . ', t1.task_duration_type)) / SUM(t1.task_duration * IF(t1.task_duration_type = 24, ' . $working_hours . ', t1.task_duration_type)) AS project_percent_complete');
			$q->addJoin('tasks', 't1', 'projects.project_id = t1.task_project', 'inner');
			$q->addWhere('project_id = ' . $oid . ' AND t1.task_id = t1.task_parent');
			$this->project_percent_complete = $q->loadResult();
		}
		return $result;
	}

	/*
	 * DEPRECATED
	 */
	public function fullLoad($projectId) {
		$this->loadFull($projectId);
	}
	public function loadFull($projectId) {
		global $w2Pconfig;

		$working_hours = ($w2Pconfig['daily_working_hours'] ? $w2Pconfig['daily_working_hours'] : 8);

		// GJB: Note that we have to special case duration type 24 and this refers to the hours in a day, NOT 24 hours
		$q = new DBQuery;
		$q->addTable('projects');
		$q->addQuery('company_name, CONCAT_WS(\' \',contact_first_name,contact_last_name) user_name, projects.*');
		$q->addQuery('SUM(t1.task_duration * t1.task_percent_complete * IF(t1.task_duration_type = 24, ' . $working_hours . ', t1.task_duration_type)) / SUM(t1.task_duration * IF(t1.task_duration_type = 24, ' . $working_hours . ', t1.task_duration_type)) AS project_percent_complete');
		$q->addJoin('tasks', 't1', 'projects.project_id = t1.task_project', 'left');
		$q->addJoin('companies', 'com', 'company_id = project_company', 'inner');
		$q->leftJoin('users', 'u', 'user_id = project_owner');
		$q->leftJoin('contacts', 'con', 'contact_id = user_contact');
		$q->addWhere('project_id = ' . (int) $projectId);
		$q->addGroup('project_id');

		$this->company_name = '';
		$this->user_name = '';
		$q->loadObject($this);
	}
	// overload canDelete
	public function canDelete(&$msg, $oid = null) {
		// TODO: check if user permissions are considered when deleting a project
		global $AppUI;
		$perms = &$AppUI->acl();

		return $perms->checkModuleItem('projects', 'delete', $oid);

		// NOTE: I uncommented the dependencies check since it is
		// very anoying having to delete all tasks before being able
		// to delete a project.

		/*
		$tables[] = array( 'label' => 'Tasks', 'name' => 'tasks', 'idfield' => 'task_id', 'joinfield' => 'task_project' );
		// call the parent class method to assign the oid
		return CW2pObject::canDelete( $msg, $oid, $tables );
		*/
	}

	public function delete() {
		$this->load($this->project_id);
		addHistory('projects', $this->project_id, 'delete', $this->project_name, $this->project_id);
		$q = new DBQuery;
		$q->addTable('tasks');
		$q->addQuery('task_id');
		$q->addWhere('task_project = ' . (int)$this->project_id);
		$tasks_to_delete = $q->loadColumn();
		$q->clear();
		foreach ($tasks_to_delete as $task_id) {
			$q->setDelete('user_tasks');
			$q->addWhere('task_id =' . $task_id);
			$q->exec();
			$q->clear();
			$q->setDelete('task_dependencies');
			$q->addWhere('dependencies_req_task_id =' . (int)$task_id);
			$q->exec();
			$q->clear();
		}
		$q->setDelete('tasks');
		$q->addWhere('task_project =' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		$q = new DBQuery;
		$q->addTable('files');
		$q->addQuery('file_id');
		$q->addWhere('file_project = ' . (int)$this->project_id);
		$files_to_delete = $q->loadColumn();
		$q->clear();
		foreach ($files_to_delete as $file_id) {
			$file = new CFile();
			$file->file_id = $file_id;
			$file->file_project = (int)$this->project_id;
			$file->delete();
		}
		$q->setDelete('events');
		$q->addWhere('event_project =' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		// remove the project-contacts and project-departments map
		$q->setDelete('project_contacts');
		$q->addWhere('project_id =' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		$q->setDelete('project_departments');
		$q->addWhere('project_id =' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		$q->setDelete('projects');
		$q->addWhere('project_id =' . (int)$this->project_id);

		if (!$q->exec()) {
			$result = db_error();
		} else {
			$result = null;
		}
		$q->clear();
		return $result;
	}

	/**	Import tasks from another project
	 *
	 *	@param	int		Project ID of the tasks come from.
	 *	@return	bool	
	 **/
	public function importTasks($from_project_id) {

		// Load the original
		$origProject = new CProject();
		$origProject->load($from_project_id);
		$q = new DBQuery;
		$q->addTable('tasks');
		$q->addQuery('task_id');
		$q->addWhere('task_project =' . (int)$from_project_id);
		$tasks = array_flip($q->loadColumn());
		$q->clear();

		$origDate = new CDate($origProject->project_start_date);

		$destDate = new CDate($this->project_start_date);

		$timeOffset = $origDate->dateDiff($destDate);
		if ($origDate->compare($origDate, $destDate) > 0) {
			$timeOffset = -1 * $timeOffset;
		}

		// Dependencies array
		$deps = array();

		// Copy each task into this project and get their deps
		foreach ($tasks as $orig => $void) {
			$objTask = new CTask();
			$objTask->load($orig);
			$destTask = $objTask->copy($this->project_id);
			$tasks[$orig] = $destTask;
			$deps[$orig] = $objTask->getDependencies();
		}

		// Fix record integrity
		foreach ($tasks as $old_id => $newTask) {

			// Fix parent Task
			// This task had a parent task, adjust it to new parent task_id
			if ($newTask->task_id != $newTask->task_parent) {
				$newTask->task_parent = $tasks[$newTask->task_parent]->task_id;
			}

			// Fix task start date from project start date offset
			$origDate->setDate($newTask->task_start_date);
			$origDate->addDays($timeOffset);
			$destDate = $origDate;
			$newTask->task_start_date = $destDate->format(FMT_DATETIME_MYSQL);

			// Fix task end date from start date + work duration
			if (!empty($newTask->task_end_date) && $newTask->task_end_date != '0000-00-00 00:00:00') {
				$origDate->setDate($newTask->task_end_date);
				$origDate->addDays($timeOffset);
				$destDate = $origDate;
				$newTask->task_end_date = $destDate->format(FMT_DATETIME_MYSQL);
			}

			// Dependencies
			if (!empty($deps[$old_id])) {
				$oldDeps = explode(',', $deps[$old_id]);
				// New dependencies array
				$newDeps = array();
				foreach ($oldDeps as $dep) {
					$newDeps[] = $tasks[$dep]->task_id;
				}

				// Update the new task dependencies
				$csList = implode(',', $newDeps);
				$newTask->updateDependencies($csList);
			} // end of update dependencies
			$newTask->store();
		} // end Fix record integrity

	} // end of importTasks

	/**
	 **	Overload of the w2PObject::getAllowedRecords 
	 **	to ensure that the allowed projects are owned by allowed companies.
	 **
	 **	@author	handco <handco@sourceforge.net>
	 **	@see	w2PObject::getAllowedRecords
	 **/

	public function getAllowedRecords($uid, $fields = '*', $orderby = '', $index = null, $extra = null, $table_alias = '') {
		$oCpy = new CCompany();

		$aCpies = $oCpy->getAllowedRecords($uid, 'company_id, company_name');
		if (count($aCpies)) {
			$buffer = '(project_company IN (' . implode(',', array_keys($aCpies)) . '))';

			if (!isset($extra['from']) && !isset($extra['join'])) {
				$extra['join'] = 'project_departments';
				$extra['on'] = 'projects.project_id = project_departments.project_id';
			} elseif ($extra['from'] != 'project_departments' && !isset($extra['join'])) {
				$extra['join'] = 'project_departments';
				$extra['on'] = 'projects.project_id = project_departments.project_id';
			}
			//Department permissions
			$oDpt = new CDepartment();
			$aDpts = $oDpt->getAllowedRecords($uid, 'dept_id, dept_name');
			if (count($aDpts)) {
				$dpt_buffer = '(department_id IN (' . implode(',', array_keys($aDpts)) . ') OR department_id IS NULL)';
			} else {
				// There are no allowed departments, so allow projects with no department.
				$dpt_buffer = '(department_id IS NULL)';
			}

			if (isset($extra['where']) && $extra['where'] != '') {
				$extra['where'] = $extra['where'] . ' AND ' . $buffer . ' AND ' . $dpt_buffer;
			} else {
				$extra['where'] = $buffer . ' AND ' . $dpt_buffer;
			}
		} else {
			// There are no allowed companies, so don't allow projects.
			if ($extra['where'] != '') {
				$extra['where'] = $extra['where'] . ' AND 1 = 0 ';
			} else {
				$extra['where'] = '1 = 0';
			}
		}
		return parent::getAllowedRecords($uid, $fields, $orderby, $index, $extra, $table_alias);

	}

	public function getAllowedSQL($uid, $index = null) {
		$oCpy = new CCompany();
		$where = $oCpy->getAllowedSQL($uid, 'project_company');

		$oDpt = new CDepartment();
		$where += $oDpt->getAllowedSQL($uid, 'dept_id');

		$project_where = parent::getAllowedSQL($uid, $index);
		return array_merge($where, $project_where);
	}

	public function setAllowedSQL($uid, &$query, $index = null, $key = 'pr') {
		$oCpy = new CCompany;
		parent::setAllowedSQL($uid, $query, $index, $key);
		$oCpy->setAllowedSQL($uid, $query, ($key ? $key . '.' : '').'project_company');
		//Department permissions
		$oDpt = new CDepartment();
		$query->leftJoin('project_departments', '', $key.'.project_id = project_departments.project_id');
		$oDpt->setAllowedSQL($uid, $query, 'project_departments.department_id');
	}

	/**
	 *	Overload of the w2PObject::getDeniedRecords 
	 *	to ensure that the projects owned by denied companies are denied.
	 *
	 *	@author	handco <handco@sourceforge.net>
	 *	@see	w2PObject::getAllowedRecords
	 */
	public function getDeniedRecords($uid) {
		$aBuf1 = parent::getDeniedRecords($uid);

		$oCpy = new CCompany();
		// Retrieve which projects are allowed due to the company rules
		$aCpiesAllowed = $oCpy->getAllowedRecords($uid, 'company_id,company_name');

		//Department permissions
		$oDpt = new CDepartment();
		$aDptsAllowed = $oDpt->getAllowedRecords($uid, 'dept_id,dept_name');

		$q = new DBQuery;
		$q->addTable('projects');
		$q->addQuery('projects.project_id');
		$q->addJoin('project_departments', 'pd', 'pd.project_id = projects.project_id');

		if (count($aCpiesAllowed)) {
			if ((array_search('0', $aCpiesAllowed)) === false) {
				//If 0 (All Items of a module) are not permited then just add the allowed items only
				$q->addWhere('NOT (project_company IN (' . implode(',', array_keys($aCpiesAllowed)) . '))');
			} else {
				//If 0 (All Items of a module) are permited then don't add a where clause so the user is permitted to see all
			}
		} else {
			//if the user is not allowed any company then lets shut him off
			$q->addWhere('0=1');
		}

		if (count($aDptsAllowed)) {
			if ((array_search('0', $aDptsAllowed)) === false) {
				//If 0 (All Items of a module) are not permited then just add the allowed items only
				$q->addWhere('NOT (department_id IN (' . implode(',', array_keys($aDptsAllowed)) . '))');
			} else {
				//If 0 (All Items of a module) are permited then don't add a where clause so the user is permitted to see all
				$q->addWhere('NOT (department_id IS NULL)');
			}
		} else {
			//If 0 (All Items of a module) are permited then don't add a where clause so the user is permitted to see all
			$q->addWhere('NOT (department_id IS NULL)');
		}

		$aBuf2 = $q->loadColumn();
		$q->clear();

		return array_merge($aBuf1, $aBuf2);

	}
	public function getAllowedProjectsInRows($userId) {
		$q = new DBQuery;
		$q->addQuery('pr.project_id, project_status, project_name, project_description, project_short_name');
		$q->addTable('projects', 'pr');
		$q->addOrder('project_short_name');
		$this->setAllowedSQL($userId, $q, null, 'pr');
		$allowedProjectRows = $q->exec();

		return $allowedProjectRows;
	}

	/** Retrieve tasks with latest task_end_dates within given project
	 * @param int Project_id
	 * @param int SQL-limit to limit the number of returned tasks
	 * @return array List of criticalTasks
	 */
	public function getCriticalTasks($project_id = null, $limit = 1) {
		$project_id = !empty($project_id) ? $project_id : $this->project_id;
		$q = new DBQuery;
		$q->addTable('tasks');
		$q->addWhere('task_project = ' . (int)$project_id . ' AND task_end_date IS NOT NULL AND task_end_date <>  \'0000-00-00 00:00:00\'');
		$q->addOrder('task_end_date DESC');
		$q->setLimit($limit);

		return $q->loadList();
	}

	public function store() {

		$this->w2PTrimAll();

		$this->project_target_budget = str_replace(',', '', $this->project_target_budget);
		$errorMsgArray = $this->check();

		if (count($errorMsgArray) > 0) {
      return $errorMsgArray;
		}

		if ($this->project_id) {
			$q = new DBQuery;
            $this->project_updated = $q->dbfnNow();
			$ret = $q->updateObject('projects', $this, 'project_id', false);
			$q->clear();
			addHistory('projects', $this->project_id, 'update', $this->project_name, $this->project_id);
		} else {
			$q = new DBQuery;
            $this->project_updated = $q->dbfnNow();
            $this->project_created = $q->dbfnNow();
			$ret = $q->insertObject('projects', $this, 'project_id');
			$q->clear();
			addHistory('projects', $this->project_id, 'add', $this->project_name, $this->project_id);
		}

		//split out related departments and store them seperatly.
		$q = new DBQuery;
		$q->setDelete('project_departments');
		$q->addWhere('project_id=' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		if ($this->project_departments) {
			$departments = explode(',', $this->project_departments);
			foreach ($departments as $department) {
				$q->addTable('project_departments');
				$q->addInsert('project_id', $this->project_id);
				$q->addInsert('department_id', $department);
				$q->exec();
				$q->clear();
			}
		}

		//split out related contacts and store them seperatly.
		$q->setDelete('project_contacts');
		$q->addWhere('project_id=' . (int)$this->project_id);
		$q->exec();
		$q->clear();
		if ($this->project_contacts) {
			$contacts = explode(',', $this->project_contacts);
			foreach ($contacts as $contact) {
				if ($contact) {
					$q->addTable('project_contacts');
					$q->addInsert('project_id', $this->project_id);
					$q->addInsert('contact_id', $contact);
					$q->exec();
					$q->clear();
				}
			}
		}

		if (!$ret) {
			return get_class($this) . '::store failed ' . db_error();
		} else {
			return null;
		}

	}

	public function notifyOwner($isNotNew) {
		global $AppUI, $w2Pconfig, $locale_char_set;

		$mail = new Mail;

		if (intval($isNotNew)) {
			$mail->Subject("Project Updated: $this->project_name ", $locale_char_set);
		} else {
			$mail->Subject("Project Submitted: $this->project_name ", $locale_char_set);
		}

		$q = new DBQuery;
		$q->addTable('projects', 'p');
		$q->addQuery('p.project_id');
		$q->addQuery('oc.contact_email as owner_email, oc.contact_first_name as owner_first_name, oc.contact_last_name as owner_last_name');
		$q->leftJoin('users', 'o', 'o.user_id = p.project_owner');
		$q->leftJoin('contacts', 'oc', 'oc.contact_id = o.user_contact');
		$q->addWhere('p.project_id = ' . (int)$this->project_id);
		$users = $q->loadList();
		$q->clear();

		if (count($users)) {
			if (intval($isNotNew)) {
				$body = $AppUI->_('Project') . ": $this->project_name Has Been Updated Via Project Manager. You can view the Project by clicking: ";
			} else {
				$body = $AppUI->_('Project') . ": $this->project_name Has Been Submitted Via Project Manager. You can view the Project by clicking: ";
			}
			$body .= "\n" . $AppUI->_('URL') . ':     ' . w2PgetConfig('base_url') . '/index.php?m=projects&a=view&project_id=' . $this->project_id;
			$body .= "\n\n(You are receiving this email because you are the owner to this project)";
			$body .= "\n\n" . $AppUI->_('Description') . ':' . "\n$this->project_description";
			if (intval($isNotNew)) {
				$body .= "\n\n" . $AppUI->_('Updater') . ': ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			} else {
				$body .= "\n\n" . $AppUI->_('Creator') . ': ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			}

			if ($this->_message == 'deleted') {
				$body .= "\n\nProject " . $this->project_name . ' was ' . $this->_message . ' by ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			}

			$mail->Body($body, isset($GLOBALS['locale_char_set']) ? $GLOBALS['locale_char_set'] : '');
		}
		if ($mail->ValidEmail($users[0]['owner_email'])) {
			$mail->To($users[0]['owner_email'], true);
			$mail->Send();
		}

		return '';
	}

	public function notifyContacts($isNotNew) {
		global $AppUI, $w2Pconfig, $locale_char_set;

		$subject = (intval($isNotNew)) ? "Project Updated: $this->project_name " : "Project Submitted: $this->project_name ";

		$users = CProject::getContacts($AppUI, $this->project_id);

		if (count($users)) {
			if (intval($isNotNew)) {
				$body = $AppUI->_('Project') . ": $this->project_name Has Been Updated Via Project Manager. You can view the Project by clicking: ";
			} else {
				$body = $AppUI->_('Project') . ": $this->project_name Has Been Submitted Via Project Manager. You can view the Project by clicking: ";
			}
			$body .= "\n" . $AppUI->_('URL') . ':     ' . w2PgetConfig('base_url') . '/index.php?m=projects&a=view&project_id=' . $this->project_id;
			$body .= "\n\n(You are receiving this message because you are a contact or assignee for this Project)";
			$body .= "\n\n" . $AppUI->_('Description') . ':' . "\n$this->project_description";
			if (intval($isNotNew)) {
				$body .= "\n\n" . $AppUI->_('Updater') . ': ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			} else {
				$body .= "\n\n" . $AppUI->_('Creator') . ': ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			}

			if ($this->_message == 'deleted') {
				$body .= "\n\nProject " . $this->project_name . ' was ' . $this->_message . ' by ' . $AppUI->user_first_name . ' ' . $AppUI->user_last_name;
			}

			foreach ($users as $row) {
				$mail = new Mail;
				$mail->Body($body, isset($GLOBALS['locale_char_set']) ? $GLOBALS['locale_char_set'] : '');
				$mail->Subject($subject, $locale_char_set);

				if ($mail->ValidEmail($row['contact_email'])) {
					$mail->To($row['contact_email'], true);
					$mail->Send();
				}
			}
		}
		return '';
	}
	public function getAllowedProjects($userId, $activeOnly = true) {
		$q = new DBQuery;
		$q->addTable('projects', 'pr');
		$q->addQuery('pr.project_id, project_color_identifier, project_name, project_start_date, project_end_date');
		if ($activeOnly) {
			$q->addWhere('project_active = 1');
		}
		$q->addGroup('pr.project_id');
		$q->addOrder('project_name');
		$this->setAllowedSQL($userId, $q, null, 'pr');
		
		return $q->loadHashList('project_id');
	}
	public static function getContacts($AppUI, $projectId) {
		$perms = $AppUI->acl();

		if ($AppUI->isActiveModule('contacts') && $perms->checkModule('contacts', 'view')) {
			$q = new DBQuery;
			$q->addTable('contacts', 'c');
			$q->addJoin('project_contacts', 'pc', 'pc.contact_id = c.contact_id', 'inner');
			$q->leftJoin('departments', 'd', 'd.dept_id = c.contact_department');
			$q->addWhere('pc.project_id = ' . (int) $projectId);
			$q->addQuery('c.contact_id, contact_first_name, contact_last_name, contact_email');
			$q->addQuery('contact_phone, dept_name');
			$q->addWhere('
				(contact_private=0
					OR (contact_private=1 AND contact_owner=' . $AppUI->user_id . ')
					OR contact_owner IS NULL OR contact_owner = 0
				)');

			$department = new CDepartment;
			$department->setAllowedSQL($AppUI->user_id, $q);
			return $q->loadHashList('contact_id');
		}
	}
	public static function getDepartments($AppUI, $projectId) {
		$perms = $AppUI->acl();

		if ($AppUI->isActiveModule('departments') && $perms->checkModule('departments', 'view')) {
			$q = new DBQuery;
			$q->addTable('departments', 'a');
			$q->addTable('project_departments', 'b');
			$q->addQuery('a.dept_id, a.dept_name, a.dept_phone');
			$q->addWhere('a.dept_id = b.department_id and b.project_id = ' . (int) $projectId);

			$department = new CDepartment;
			$department->setAllowedSQL($AppUI->user_id, $q);

			return $q->loadHashList('dept_id');
		}
	}
	public static function getForums($AppUI, $projectId) {
		$perms = $AppUI->acl();

		if ($AppUI->isActiveModule('forums') && $perms->checkModule('forums', 'view')) {
			$q = new DBQuery;
			$q->addTable('forums');
			$q->addQuery('forum_id, forum_project, forum_description, forum_owner, forum_name, forum_message_count,
				DATE_FORMAT(forum_last_date, "%d-%b-%Y %H:%i" ) forum_last_date,
				project_name, project_color_identifier, project_id');
			$q->addJoin('projects', 'p', 'project_id = forum_project', 'inner');
			$q->addWhere('forum_project = ' . (int) $projectId);
			$q->addOrder('forum_project, forum_name');

			return $q->loadHashList('forum_id');
		}
	}
	public static function getCompany($projectId) {
		$q = new DBQuery;
		$q->addQuery('project_company');
		$q->addTable('projects');
		$q->addWhere('project_id = ' . (int) $projectId);

		return $q->loadResult();
	}
	public static function getBillingCodes($companyId, $all = false) {
		$q = new DBQuery;
		$q->addTable('billingcode');
		$q->addQuery('billingcode_id, billingcode_name');
		$q->addOrder('billingcode_name');
		$q->addWhere('billingcode_status = 0');
		$q->addWhere('(company_id = 0 OR company_id = ' . (int) $companyId . ')');
		$task_log_costcodes = $q->loadHashList();

		if ($all) {
			$q->clear();
			$q->addTable('billingcode');
			$q->addQuery('billingcode_id, billingcode_name');
			$q->addOrder('billingcode_name');
			$q->addWhere('billingcode_status = 1');
			$q->addWhere('(company_id = 0 OR company_id = ' . (int) $companyId . ')');

			$billingCodeList = $q->loadHashList();
			foreach($billingCodeList as $id => $code) {
				$task_log_costcodes[$id] = $code;
			}			
		}

		return $task_log_costcodes;
	}
	public static function getOwners() {
		$q = new DBQuery();
		$q->addTable('projects', 'p');
		$q->addQuery('user_id, concat(contact_first_name, \' \', contact_last_name)');
		$q->leftJoin('users', 'u', 'u.user_id = p.project_owner');
		$q->leftJoin('contacts', 'c', 'c.contact_id = u.user_contact');
		$q->addOrder('contact_first_name, contact_last_name');
		$q->addWhere('user_id > 0');
		$q->addWhere('p.project_owner IS NOT NULL');

		return $q->loadHashList();
	}
	public static function updateStatus($AppUI, $projectId, $statusId) {
		$perms = $AppUI->acl();

		if ($perms->checkModuleItem('projects', 'edit', $projectId) && $projectId > 0 && $statusId > 0) {
			$q = new DBQuery;
			$q->addTable('projects');
			$q->addUpdate('project_status', $statusId);
			$q->addWhere('project_id   = ' . (int) $projectId);
			$q->exec();
		}
	}

  public static function updateTaskCount($projectId, $taskCount) {

  	if (intval($projectId) > 0 && intval($taskCount)) {
      $q = new DBQuery;
      $q->addTable('projects');
      $q->addUpdate('project_task_count', intval($taskCount));
      $q->addWhere('project_id   = ' . (int) $projectId);
      $q->exec();
  	}
  }

	public function hasChildProjects($projectId = 0) {
		// Note that this returns the *count* of projects.  If this is zero, it 
		//   is evaluated as false, otherwise it is considered true.
		$q = new DBQuery();
		$q->addTable('projects');
		$q->addQuery('COUNT(project_id)');
		if ($projectId > 0) {
			$q->addWhere('project_original_parent = ' . $projectId);
		} else {
			$q->addWhere('project_original_parent = ' . (int)($this->project_original_parent ? $this->project_original_parent : $this->project_id));
		}
		
		// I hate how this one works... since the default project parent is 
		//   itself, so this will always have at least one result. 
		return $q->loadResult()-1;
	}
	
	public static function hasTasks($projectId) {
		// Note that this returns the *count* of tasks.  If this is zero, it is 
		//   evaluated as false, otherwise it is considered true.
		$q = new DBQuery;
		$q->addTable('tasks');
		$q->addQuery('COUNT(distinct tasks.task_id) AS total_tasks');
		$q->addWhere('task_project = ' . (int) $projectId);

		return $q->loadResult();
	}
	public function getWorkedHours() {
		// now milestones are summed up, too, for consistence with the tasks duration sum
		// the sums have to be rounded to prevent the sum form having many (unwanted) decimals because of the mysql floating point issue
		// more info on http://www.mysql.com/doc/en/Problems_with_float.html
		$q = new DBQuery;
		$q->addTable('task_log');
		$q->addTable('tasks');
		$q->addQuery('ROUND(SUM(task_log_hours),2)');
		$q->addWhere('task_log_task = task_id AND task_project = ' . (int) $this->project_id);
		$worked_hours = 0 + $q->loadResult();

		return rtrim($worked_hours, '.');
	}
	public function getTotalHours() {
		global $w2Pconfig;

		// now milestones are summed up, too, for consistence with the tasks duration sum
		// the sums have to be rounded to prevent the sum form having many (unwanted) decimals because of the mysql floating point issue
		// more info on http://www.mysql.com/doc/en/Problems_with_float.html
		$q = new DBQuery;
		$q->addTable('tasks');
		$q->addQuery('ROUND(SUM(task_duration),2)');
		$q->addWhere('task_project = ' . (int) $this->project_id . ' AND task_duration_type = 24 AND task_dynamic <> 1');
		$days = $q->loadResult();
		$q->clear();
	
		$q->addTable('tasks');
		$q->addQuery('ROUND(SUM(task_duration),2)');
		$q->addWhere('task_project = ' . (int) $this->project_id . ' AND task_duration_type = 1 AND task_dynamic <> 1');
		$hours = $q->loadResult();
		return $days * $w2Pconfig['daily_working_hours'] + $hours;
	}
	public function getTotalProjectHours() {
		global $w2Pconfig;

		// now milestones are summed up, too, for consistence with the tasks duration sum
		// the sums have to be rounded to prevent the sum form having many (unwanted) decimals because of the mysql floating point issue
		// more info on http://www.mysql.com/doc/en/Problems_with_float.html
		
		// I'm really not sure why this is calculated and treated differently 
		//   from the "total hours" calculation above.  I simply copied this from 
		//  the projects/view.php file to get data calls out of the view.  Any 
		//  further info or explanation would be appreciated. - caseydk
		$total_project_hours = 0;

		$q = new DBQuery;
		$q->addTable('tasks', 't');
		$q->addQuery('ROUND(SUM(t.task_duration*u.perc_assignment/100),2)');
		$q->addJoin('user_tasks', 'u', 't.task_id = u.task_id', 'inner');
		$q->addWhere('t.task_project = ' . (int) $this->project_id . ' AND t.task_duration_type = 24 AND t.task_dynamic <> 1');
		$total_project_days = $q->loadResult();
		$q->clear();
	
		$q->addTable('tasks', 't');
		$q->addQuery('ROUND(SUM(t.task_duration*u.perc_assignment/100),2)');
		$q->addJoin('user_tasks', 'u', 't.task_id = u.task_id', 'inner');
		$q->addWhere('t.task_project = ' . (int) $this->project_id . ' AND t.task_duration_type = 1 AND t.task_dynamic <> 1');
		$total_project_hours = $q->loadResult();
		$total_project_hours = $total_project_days * $w2Pconfig['daily_working_hours'] + $total_project_hours;

		return rtrim($total_project_hours, '.');
	}
	public function getTaskLogs($AppUI, $projectId, $user_id = 0, $hide_inactive = false, $hide_complete = false, $cost_code = 0) {

		$q = new DBQuery;
		$q->addTable('task_log');
		$q->addQuery('task_log.*, user_username, task_id');
		$q->addQuery("CONCAT(contact_first_name, ' ', contact_last_name) AS real_name");
		$q->addQuery('billingcode_name as task_log_costcode');
		$q->addJoin('users', 'u', 'user_id = task_log_creator');
		$q->addJoin('tasks', 't', 'task_log_task = t.task_id');
		$q->addJoin('contacts', 'ct', 'contact_id = user_contact');
		$q->addJoin('billingcode', 'b', 'task_log.task_log_costcode = billingcode_id');

		$q->addWhere('task_project = ' . (int) $projectId);
		if ($user_id > 0) {
			$q->addWhere('task_log_creator=' . $user_id);
		}
		if ($hide_inactive) {
			$q->addWhere('task_status>=0');
		}
		if ($hide_complete) {
			$q->addWhere('task_percent_complete < 100');
		}
		if ($cost_code > 0) {
			$q->addWhere("billingcode_id = $cost_code");
		}
		$q->addOrder('task_log_date');
		$this->setAllowedSQL($AppUI->user_id, $q, 'task_project');

		return $q->loadList();
	}
  
  public function hook_search() {
    $search['table'] = 'projects';
    $search['table_alias'] = 'p';
    $search['table_module'] = 'projects';
    $search['table_key'] = 'p.project_id'; // primary key in searched table
    $search['table_link'] = 'index.php?m=projects&a=view&project_id='; // first part of link
    $search['table_title'] = 'Projects';
    $search['table_orderby'] = 'project_name';
    $search['search_fields'] = array('p.project_id', 'p.project_name', 'p.project_short_name', 'p.project_location', 'p.project_description', 'p.project_url', 'p.project_demo_url', 'con.contact_last_name', 'con.contact_first_name', 'con.contact_email', 'con.contact_title', 'con.contact_email2', 'con.contact_phone', 'con.contact_phone2', 'con.contact_address1', 'con.contact_notes');
    $search['display_fields'] = array('p.project_id', 'p.project_name', 'p.project_short_name', 'p.project_location', 'p.project_description', 'p.project_url', 'p.project_demo_url', 'con.contact_last_name', 'con.contact_first_name', 'con.contact_email', 'con.contact_title', 'con.contact_email2', 'con.contact_phone', 'con.contact_phone2', 'con.contact_address1', 'con.contact_notes');
    $search['table_joins'] = array(array('table' => 'project_contacts', 'alias' => 'pc', 'join' => 'p.project_id = pc.project_id'), array('table' => 'contacts', 'alias' => 'con', 'join' => 'pc.contact_id = con.contact_id'));

    return $search;
  }
}

/* The next lines of code have resided in projects/index.php before
** and have been moved into this 'encapsulated' function
** for reusability of that central code.
**
** @date 20060225
** @responsible gregorerhardt
**
** E.g. this code is used as well in a tab for the admin/viewuser site
**
** @mixed user_id 	userId as filter for tasks/projects that are shown, if nothing is specified, 
current viewing user $AppUI->user_id is used.
*/

function projects_list_data($user_id = false) {
	global $AppUI, $addPwOiD, $buffer, $company, $company_id, $company_prefix, $deny, $department, $dept_ids, $w2Pconfig, $orderby, $orderdir, $projects, $tasks_critical, $tasks_problems, $tasks_sum, $tasks_summy, $tasks_total, $owner, $projectTypeId, $search_text, $project_type;

	$addProjectsWithAssignedTasks = $AppUI->getState('addProjWithTasks') ? $AppUI->getState('addProjWithTasks') : 0;

	// get any records denied from viewing
	$obj = new CProject();
	$deny = $obj->getDeniedRecords($AppUI->user_id);

	// Let's delete temproary tables
	$q = new DBQuery;
	// Let's delete support tables data
	$q->setDelete('tasks_sum');
	$q->exec();
	$q->clear();

  //BEGIN: Deprecated in v2.0 
	$q->setDelete('tasks_total');
	$q->exec();
	$q->clear();
  //END: Deprecated in v2.0

	$q->setDelete('tasks_summy');
	$q->exec();
	$q->clear();

	$q->setDelete('tasks_critical');
	$q->exec();
	$q->clear();

	$q->setDelete('tasks_problems');
	$q->exec();
	$q->clear();

	$q->setDelete('tasks_users');
	$q->exec();
	$q->clear();

	// support task sum table
	// by Pablo Roca (pabloroca@mvps.org)
	// 16 August 2003
	$working_hours = ($w2Pconfig['daily_working_hours'] ? $w2Pconfig['daily_working_hours'] : 8);

	// GJB: Note that we have to special case duration type 24 and this refers to the hours in a day, NOT 24 hours
	$q->addInsertSelect('tasks_sum');
	$q->addTable('tasks');
	$q->addQuery('task_project, COUNT(distinct tasks.task_id) AS total_tasks'); 
	$q->addQuery('SUM(task_duration * task_percent_complete * IF(task_duration_type = 24, ' . $working_hours . ', task_duration_type)) / SUM(task_duration * IF(task_duration_type = 24, ' . $working_hours . ', task_duration_type)) AS project_percent_complete');
	$q->addQuery('SUM(task_duration * IF(task_duration_type = 24, ' . $working_hours . ', task_duration_type)) AS project_duration');
	if ($user_id) {
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = tasks.task_id');
		$q->addWhere('ut.user_id = ' . (int)$user_id);
	}
	$q->addWhere('tasks.task_id = tasks.task_parent');
	$q->addGroup('task_project');
	$tasks_sum = $q->exec();
	$q->clear();

  //BEGIN: Deprecated in v2.0
	// support task total table
	$q->addInsertSelect('tasks_total');
	$q->addTable('tasks');
	$q->addQuery('task_project, COUNT(distinct tasks.task_id) AS total_tasks');
	if ($user_id) {
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = tasks.task_id');
		$q->addWhere('ut.user_id = ' . (int)$user_id);
	}
	$q->addGroup('task_project');
	$tasks_total = $q->exec();
	$q->clear();
  //END: Deprecated in v2.0

	// support My Tasks
	$q->addInsertSelect('tasks_summy');
	$q->addTable('tasks');
	$q->addQuery('task_project, COUNT(distinct task_id) AS my_tasks');
	if ($user_id) {
		$q->addWhere('task_owner = ' . (int)$user_id);
	} else {
		$q->addWhere('task_owner = ' . (int)$AppUI->user_id);
	}
	$q->addGroup('task_project');
	$tasks_summy = $q->exec();
	$q->clear();

	// support critical tasks
	$q->addInsertSelect('tasks_critical');
	$q->addTable('tasks', 't');
	$q->addQuery('task_project, task_id AS critical_task, task_end_date AS project_actual_end_date');
	$sq = new DBQuery;
	$sq->addTable('tasks', 'st');
	$sq->addQuery('MAX(task_end_date)');
	$sq->addWhere('st.task_project = t.task_project');
	$q->addWhere('task_end_date = (' . $sq->prepare() . ')');
	$q->addGroup('task_project');
	$tasks_critical = $q->exec();
	$q->clear();

	// support task problem logs
	$q->addInsertSelect('tasks_problems');
	$q->addTable('tasks');
	$q->addQuery('task_project, task_log_problem');
	$q->addJoin('task_log', 'tl', 'tl.task_log_task = task_id', 'inner');
	$q->addWhere('task_log_problem = 1');
	$q->addGroup('task_project');
	$tasks_problems = $q->exec();
	$q->clear();

	if ($addProjectsWithAssignedTasks) {
		// support users tasks
		$q->addInsertSelect('tasks_users');
		$q->addTable('tasks');
		$q->addQuery('task_project');
		$q->addQuery('ut.user_id');
		$q->addJoin('user_tasks', 'ut', 'ut.task_id = tasks.task_id');
		if ($user_id) {
			$q->addWhere('ut.user_id = ' . (int)$user_id);
		}
		$q->addOrder('task_end_date DESC');
		$q->addGroup('task_project');
		$tasks_users = $q->exec();
		$q->clear();
	}

	// add Projects where the Project Owner is in the given department
	if ($addPwOiD && isset($department)) {
		$owner_ids = array();
		$q->addTable('users');
		$q->addQuery('user_id');
		$q->addJoin('contacts', 'c', 'c.contact_id = user_contact', 'inner');
		$q->addWhere('c.contact_department = ' . (int)$department);
		$owner_ids = $q->loadColumn();
		$q->clear();
	}

	if (isset($department)) {
		//If a department is specified, we want to display projects from the department, and all departments under that, so we need to build that list of departments
		$dept_ids = array();
		$q->addTable('departments');
		$q->addQuery('dept_id, dept_parent');
		$q->addOrder('dept_parent,dept_name');
		$rows = $q->loadList();
		addDeptId($rows, $department);
		$dept_ids[] = isset($department->dept_id) ? $department->dept_id : 0;
		$dept_ids[] = ($department > 0) ? $department : 0;
	}
	$q->clear();

	// retrieve list of records
	// modified for speed
	// by Pablo Roca (pabloroca@mvps.org)
	// 16 August 2003
	// get the list of permitted companies
	$obj = new CCompany();
	$companies = $obj->getAllowedRecords($AppUI->user_id, 'companies.company_id,companies.company_name', 'companies.company_name');
	if (count($companies) == 0) {
		$companies = array();
	}

	$q->addTable('projects', 'pr');
	$q->addQuery('pr.project_id, project_status, project_color_identifier, project_type, project_name, project_description, project_duration, project_parent, project_original_parent, 
		project_start_date, project_end_date, project_color_identifier, project_company, company_name, company_description, project_status,
		project_priority, tc.critical_task, tc.project_actual_end_date, tp.task_log_problem, pr.project_task_count, tsy.my_tasks,
		ts.project_percent_complete, user_username, project_active');
	$q->addQuery('CONCAT(ct.contact_first_name, \' \', ct.contact_last_name) AS owner_name');
	$q->addJoin('users', 'u', 'pr.project_owner = u.user_id');
	$q->addJoin('contacts', 'ct', 'ct.contact_id = u.user_contact');
	$q->addJoin('tasks_critical', 'tc', 'pr.project_id = tc.task_project');
	$q->addJoin('tasks_problems', 'tp', 'pr.project_id = tp.task_project');
	$q->addJoin('tasks_sum', 'ts', 'pr.project_id = ts.task_project');
	$q->addJoin('tasks_summy', 'tsy', 'pr.project_id = tsy.task_project');
	if ($addProjectsWithAssignedTasks) {
		$q->addJoin('tasks_users', 'tu', 'pr.project_id = tu.task_project');
	}
	if (!isset($department) && $company_id && !$addPwOiD) {
		$q->addWhere('pr.project_company = ' . (int)$company_id);
	}
	if ($project_type > -1) {
		$q->addWhere('pr.project_type = ' . (int)$project_type);
	}
	if (isset($department) && !$addPwOiD) {
		$q->addWhere('project_departments.department_id in ( ' . implode(',', $dept_ids) . ' )');
	}
	if ($user_id && $addProjectsWithAssignedTasks) {
		$q->addWhere('(tu.user_id = ' . (int)$user_id . ' OR pr.project_owner = ' . (int)$user_id . ' )');
	} elseif ($user_id) {
		$q->addWhere('pr.project_owner = ' . (int)$user_id);
	}
	if ($owner > 0) {
		$q->addWhere('pr.project_owner = ' . (int)$owner);
	}
	if (trim($search_text)) {
		$q->addWhere('pr.project_name LIKE \'%' . $search_text . '%\' OR pr.project_description LIKE \'%' . $search_text . '%\'');
	}
	// Show Projects where the Project Owner is in the given department
	if ($addPwOiD && !empty($owner_ids)) {
		$q->addWhere('pr.project_owner IN (' . implode(',', $owner_ids) . ')');
	}

	$q->addGroup('pr.project_id');
	$q->addOrder($orderby . ' ' .$orderdir);
	$prj = new CProject();
	$prj->setAllowedSQL($AppUI->user_id, $q, null, 'pr');
	$dpt = new CDepartment();
	$projects = $q->loadList();

	// get the list of permitted companies
	$companies = arrayMerge(array('0' => $AppUI->_('All')), $companies);
	$company_array = $companies;

	//get list of all departments, filtered by the list of permitted companies.
	$q->clear();
	$q->addTable('companies');
	$q->addQuery('company_id, company_name, dep.*');
	$q->addJoin('departments', 'dep', 'companies.company_id = dep.dept_company');
	$q->addOrder('company_name,dept_parent,dept_name');
	$obj->setAllowedSQL($AppUI->user_id, $q);
	$dpt->setAllowedSQL($AppUI->user_id, $q);
	$rows = $q->loadList();

	//display the select list
	$buffer = '<select name="department" id="department" onChange="document.pickCompany.submit()" class="text" style="width: 200px;">';
	$company = '';

	foreach ($company_array as $key => $c_name) {
		$buffer .= '<option value="' . $company_prefix . $key . '" style="font-weight:bold;"' . ($company_id == $key ? 'selected="selected"' : '') . '>' . $c_name . '</option>' . "\n";
		foreach ($rows as $row) {
			if ($row['dept_parent'] == 0) {
				if ($key == $row['company_id']) {
					if ($row['dept_parent'] != null) {
						showchilddept($row);
						findchilddept($rows, $row['dept_id']);
					}
				}
			}
		}
	}
	$buffer .= '</select>';

}

function getProjects() {
	global $AppUI;
	$st_projects = array(0 => '');
	$q = new DBQuery();
	$q->addTable('projects');
	$q->addQuery('project_id, project_name, project_parent');
	$q->addOrder('project_name');
	$st_projects = $q->loadHashList('project_id');
	reset_project_parents($st_projects);
	return $st_projects;
}

function reset_project_parents(&$projects) {
	foreach ($projects as $key => $project) {
		if ($project['project_id'] == $project['project_parent'])
			$projects[$key][2] = '';
	}
}

//This kludgy function echos children projects as threads
function show_st_project(&$a, $level = 0) {
	global $st_projects_arr;
	$st_projects_arr[] = array($a, $level);
}

function find_proj_child(&$tarr, $parent, $level = 0) {
	$level = $level + 1;
	$n = count($tarr);
	for ($x = 0; $x < $n; $x++) {
		if ($tarr[$x]['project_parent'] == $parent && $tarr[$x]['project_parent'] != $tarr[$x]['project_id']) {
			show_st_project($tarr[$x], $level);
			find_proj_child($tarr, $tarr[$x]['project_id'], $level);
		}
	}
}

function getStructuredProjects($original_project_id = 0, $project_status = -1, $active_only = false) {
	global $AppUI, $st_projects_arr;
	$st_projects = array(0 => '');
	$q = new DBQuery();
	$q->addTable('projects');
	$q->addJoin('companies', '', 'projects.project_company = company_id', 'inner');
	$q->addJoin('project_departments', 'pd', 'pd.project_id = projects.project_id');
	$q->addJoin('departments', 'dep', 'pd.department_id = dep.dept_id');
	$q->addQuery('projects.project_id, project_name, project_parent');
	if ($original_project_id) {
		$q->addWhere('project_original_parent = ' . (int)$original_project_id);
	}
	if ($project_status >= 0) {
		$q->addWhere('project_status = ' . (int)$project_status);
	}
	if ($active_only) {
		$q->addWhere('project_active = 1');
	}
	$q->addOrder('project_name');

	$obj = new CCompany();
	$obj->setAllowedSQL($AppUI->user_id, $q);
	$dpt = new CDepartment();
	$dpt->setAllowedSQL($AppUI->user_id, $q);

	$st_projects = $q->loadList();
	$tnums = count($st_projects);
	for ($i = 0; $i < $tnums; $i++) {
		$st_project = $st_projects[$i];
		if (($st_project['project_parent'] == $st_project['project_id'])) {
			show_st_project($st_project);
			find_proj_child($st_projects, $st_project['project_id']);
		}
	}
}

/**
 * getProjectIndex() gets the key nr of a project record within an array of projects finding its primary key within the records so that you can call that array record to get the projects data
 *
 * @param mixed $arraylist array list of project elements to search
 * @param mixed $project_id project id to search for
 * @return int returns the array key of the project record in the array list or false if not found 
 */
function getProjectIndex($arraylist, $project_id) {
	$result = false;
	foreach ($arraylist as $key => $data) {
		if ($data['project_id'] == $project_id) {
			return $key;
		}
	}
	return $result;
}

/**
 * getDepartmentSelectionList() returns a tree of departments in <option> tags (originally used on the addedit interface to display the departments of a project)
 * 
 * @param mixed $company_id the id of the company we are searching departments
 * @param mixed $checked_array an array with the ids of the departments that should be selected on the list
 * @param integer $dept_parent used when to determine the starting level on the tree, or by recursion
 * @param integer $spaces used by recursion to add spaces to form the visual tree on the <select> element
 * @return string returns the html <option> elements
 */
function getDepartmentSelectionList($company_id, $checked_array = array(), $dept_parent = 0, $spaces = 0) {
	global $departments_count, $AppUI;
	$parsed = '';

	if ($departments_count < 6) {
		$departments_count++;
	}

	$depts_list = CDepartment::getDepartmentList($AppUI, $company_id, $dept_parent);

	foreach ($depts_list as $dept_id => $dept_info) {
		$selected = in_array($dept_id, $checked_array) ? ' selected="selected"' : '';

		$parsed .= '<option value="' . $dept_id . '"' . $selected . '>' . str_repeat('&nbsp;', $spaces) . $dept_info['dept_name'] . '</option>';
		$parsed .= getDepartmentSelectionList($company_id, $checked_array, $dept_id, $spaces + 5);
	}

	return $parsed;
}