<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * ************************************************************************************/

class Project_Module_Model extends Vtiger_Module_Model {

	public function getSideBarLinks($linkParams) {
		$linkTypes = array('SIDEBARLINK', 'SIDEBARWIDGET');
		$links = parent::getSideBarLinks($linkParams);

		$quickLinks = array();
		$quickLinks[] =	array(
		'linktype' => 'SIDEBARLINK',
		'linklabel' => 'LBL_TASKS_LIST',
		'linkurl' => $this->getTasksListUrl(),
		'linkicon' => '',
		);
		$quickLinks[] =	  array(
		'linktype' => 'SIDEBARLINK',
		'linklabel' => 'LBL_MILESTONES_LIST',
		'linkurl' => $this->getMilestonesListUrl(),
		'linkicon' => '',
		);
		if(Vtiger_DashBoard_Model::verifyDashboard($this->getName())){
			$quickLinks[] =	 array(
					   'linktype' => 'SIDEBARLINK',
					   'linklabel' => 'LBL_DASHBOARD',
					   'linkurl' => $this->getDashBoardUrl(),
					   'linkicon' => '',
			);  
		}

		foreach($quickLinks as $quickLink) {
			$links['SIDEBARLINK'][] = Vtiger_Link_Model::getInstanceFromValues($quickLink);
		}
		return $links;
	}

	public function getTasksListUrl() {
		$taskModel = Vtiger_Module_Model::getInstance('ProjectTask');
		return $taskModel->getListViewUrl();
	}
    public function getMilestonesListUrl() {
		$milestoneModel = Vtiger_Module_Model::getInstance('ProjectMilestone');
		return $milestoneModel->getListViewUrl();
	}
	public function getTimeEmployee($id) {
		$db = PearDatabase::getInstance();
		$moduleModel = Vtiger_Record_Model::getCleanInstance('OSSTimeControl');
		$Ids = $moduleModel->getProjectRelatedIDS($id);
		foreach($Ids as $module){
			foreach ($module as $moduleId){
				$idArray .= $moduleId . ',';
			}
		}
		$idArray = substr($idArray, 0, -1);
		$addSql='';
		if($idArray) {
		    $addSql=' WHERE vtiger_osstimecontrol.osstimecontrolid IN (' . $idArray . ') ';
		}
		//TODO need to handle security
		$result = $db->pquery('SELECT count(*) AS count, concat(vtiger_users.first_name, " " ,vtiger_users.last_name) as name, vtiger_users.id as id, SUM(vtiger_osstimecontrol.sum_time) as time  FROM vtiger_osstimecontrol
						INNER JOIN vtiger_crmentity ON vtiger_osstimecontrol.osstimecontrolid = vtiger_crmentity.crmid
						INNER JOIN vtiger_users ON vtiger_users.id=vtiger_crmentity.smownerid AND vtiger_users.status="ACTIVE"
						AND vtiger_crmentity.deleted = 0'.Users_Privileges_Model::getNonAdminAccessControlQuery($this->getName()).$addSql 
						. ' GROUP BY smownerid', array());

		$data = array();
		for($i=0; $i<$db->num_rows($result); $i++) {
			$row = $db->query_result_rowdata($result, $i);
			$data[] = $row;
		}
		return $data;
	}
	
	/**
	 * Function to get relation query for particular module with function name
	 * @param <record> $recordId
	 * @param <String> $functionName
	 * @param Vtiger_Module_Model $relatedModule
	 * @return <String>
	 */
	public function getRelationQuery($recordId, $functionName, $relatedModule,$relationModel = false) {
		if ($functionName === 'get_activities' || $functionName === 'get_history') {
			$userNameSql = getSqlForNameInDisplayFormat(array('first_name' => 'vtiger_users.first_name', 'last_name' => 'vtiger_users.last_name'), 'Users');

			$query = "SELECT CASE WHEN (vtiger_users.user_name not like '') THEN $userNameSql ELSE vtiger_groups.groupname END AS user_name,
						vtiger_crmentity.*, vtiger_activity.activitytype, vtiger_activity.subject, vtiger_activity.date_start, vtiger_activity.time_start,
						vtiger_activity.recurringtype, vtiger_activity.due_date, vtiger_activity.time_end, vtiger_activity.visibility,
						CASE WHEN (vtiger_activity.activitytype = 'Task') THEN (vtiger_activity.status) ELSE (vtiger_activity.eventstatus) END AS status
						FROM vtiger_activity
						INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_activity.activityid
						LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid
						LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid
							WHERE vtiger_crmentity.deleted = 0 AND vtiger_activity.process = ".$recordId;
			if($functionName === 'get_activities') {
				$query .= " AND ((vtiger_activity.activitytype='Task' and vtiger_activity.status not in ('Completed','Deferred'))
				OR (vtiger_activity.activitytype not in ('Emails','Task') and  vtiger_activity.eventstatus not in ('','Held')))";
			} else {
				$query .= " AND ((vtiger_activity.activitytype='Task' and vtiger_activity.status in ('Completed','Deferred'))
				OR (vtiger_activity.activitytype not in ('Emails','Task') and  vtiger_activity.eventstatus in ('','Held')))";
			}
			$relatedModuleName = $relatedModule->getName();
			$query .= $this->getSpecificRelationQuery($relatedModuleName);
			$instance = CRMEntity::getInstance($relatedModuleName);
			$securityParameter = $instance->getUserAccessConditionsQuerySR($relatedModuleName);
			if ($securityParameter != '')
				$sql .= ' ' . $securityParameter;
		} else {
			$query = parent::getRelationQuery($recordId, $functionName, $relatedModule, $relationModel);
		}

		return $query;
	}
	
	public function getTimeProject($id) {
		$db = PearDatabase::getInstance();
		//TODO need to handle security
		$result = $db->pquery('SELECT   vtiger_project.sum_time AS TIME,  vtiger_project.sum_time_h AS timehelpdesk,
			vtiger_project.sum_time_pt AS projecttasktime FROM  vtiger_project LEFT JOIN vtiger_crmentity   ON vtiger_project.projectid = vtiger_crmentity.crmid 
			AND vtiger_crmentity.deleted = 0 '.Users_Privileges_Model::getNonAdminAccessControlQuery($this->getName()).'WHERE vtiger_project.projectid = ?' , array($id), true);

		$response = array();
		if($db->num_rows($result)>0){
			$projectTime = $db->query_result($result, $i, 'time');
			$response[0][0] = $projectTime;
			$response[0][1] =vtranslate('Total time [h]', 'Project');
			$response[1][0] = $db->query_result($result, $i, 'timehelpdesk');
			$response[1][1] = vtranslate('Total time [Tickets]', $this->getName());
			$response[2][0] = $db->query_result($result, $i, 'projecttasktime');
			$response[2][1] =vtranslate('Total time [Project Task]', $this->getName());
		}
		
		$recordModel = Vtiger_Record_Model::getInstanceById($id, $this->getName());
		$response = array();
		$response[0][0] = $recordModel->get('sum_time');
		$response[0][1] = vtranslate('Total time [Project]', $this->getName());
		$response[1][0] = $recordModel->get('sum_time_pt');
		$response[1][1] = vtranslate('Total time [Project Task]', $this->getName());
		$response[2][0] = $recordModel->get('sum_time_h');
		$response[2][1] = vtranslate('Total time [Tickets]', $this->getName());
		$response[3][0] = $recordModel->get('sum_time_all');
		$response[3][1] = vtranslate('Total time [Sum]', $this->getName());
		return $response;
	}
	public function getGanttProject($id) {
		$adb = PearDatabase::getInstance();
		$branches = $this->getGanttMileston($id);
		$response = array();
		if($branches){
			$recordModel = Vtiger_Record_Model::getInstanceById($id, $this->getName());
			$project['id'] = $id;
			$project['text'] = $recordModel->get('projectname');
			$project['priority'] = $recordModel->get('projectpriority');
			$project['priority_label'] = vtranslate($recordModel->get('projectpriority'),$this->getName());
			$project['type'] = 'project';
			$project['module'] = $this->getName();
			$project['open'] = true;
			$response[] = $project;
			$response = array_merge($response,$branches);
		}
		return $response;
	}
	public function getGanttMileston($id) {
		$adb = PearDatabase::getInstance();
		//TODO need to handle security
		$response = array();
		$focus = CRMEntity::getInstance($this->getName());
		$relatedListMileston = $focus->get_dependents_list($id,$this->getId(),getTabid('ProjectMilestone'));
		$resultMileston = $adb->query($relatedListMileston['query']);
		$num = $adb->num_rows($resultMileston);
		for($i=0;$i<$num;$i++){
			$projectmilestone = [];
			$row = $adb->query_result_rowdata($resultMileston, $i); 
			$projectmilestone['id'] = $row['projectmilestoneid'];
			$projectmilestone['text'] = $row['projectmilestonename'];
			$projectmilestone['parent'] = $row['projectid'];
			$projectmilestone['module'] = 'ProjectMilestone';
			if($row['projectmilestonedate']){
				$endDate = strtotime(date('Y-m-d',strtotime($row['projectmilestonedate'])) . ' +1 days'); 
				$projectmilestone['start_date'] = date('d-m-Y',$endDate);
			}
			$projectmilestone['open'] = true;
			$projectmilestone['type'] = 'milestone';
			$projecttask = $this->getGanttTask($row['projectmilestoneid']);
			$response[] = $projectmilestone;
			$response = array_merge($response,$projecttask);
		}
		return $response;
	}
	public function getGanttTask($id) {
		$adb = PearDatabase::getInstance();
		//TODO need to handle security
		$response = array();
		$focus = CRMEntity::getInstance('ProjectMilestone');
		$relatedListMileston = $focus->get_dependents_list($id,getTabid('ProjectMilestone'),getTabid('ProjectTask'));
		$resultMileston = $adb->query($relatedListMileston['query']);
		$num = $adb->num_rows($resultMileston);
		for($i=0;$i<$num;$i++){
			$projecttask = [];
			$row = $adb->query_result_rowdata($resultMileston, $i); 
			$projecttask['id'] = $row['projecttaskid'];
			$projecttask['text'] = $row['projecttaskname'];
			if($row['parentid']){
				$projecttask['parent'] = $row['parentid'];
			}else{
				$projecttask['parent'] = $row['projectmilestoneid'];
			}
			$projecttask['priority'] = $row['projecttaskpriority'];
			$projecttask['priority_label'] = vtranslate($row['projecttaskpriority'],'ProjectTask');
			$projecttask['start_date'] = date('d-m-Y',strtotime($row['startdate']));
			$endDate = strtotime(date('Y-m-d',strtotime($row['targetenddate'])) . ' +1 days'); 
			$projecttask['end_date'] = date('d-m-Y',$endDate);
			$projecttask['open'] = true;
			$projecttask['type'] = 'task';
			$projecttask['module'] = 'ProjectTask';
			$response[] = $projecttask;
		}
		return $response;
	}
}
