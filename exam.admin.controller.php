<?php
/**
 * @class  examAdminController
 * @author 러키군 (admin@barch.kr)
 * @brief  exam module admin controller class
 */
class examAdminController extends exam
{
	/**
	 * @brief initialization
	 **/
	function init()
	{
	}
    /**
	 * @brief 시험 설정 저장
	 **/
    public function procExamAdminInsertModule()
	{
		// igenerate module model/controller object
		$oModuleController = getController('module');
		$oModuleModel = getModel('module');

		// setup the board module infortmation
		$args = Context::getRequestVars();
		$args->module = 'exam';
		$args->mid = $args->exam_name;
		$args->editor_colorset = $args->sel_editor_colorset;
		unset($args->exam_name);
		unset($args->sel_editor_colorset);

		// setup other variables
		if(!in_array($args->order_target,$this->order_target)) $args->order_target = 'document_srl';
		if(!in_array($args->order_type, array('asc', 'desc'))) $args->order_type = 'asc';
		if(!$args->editor_skin) $args->editor_skin = 'default';

		// if there is an existed module
		if($args->module_srl) {
			$args->hide_category = 'N';
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
			if($module_info->module_srl != $args->module_srl) unset($args->module_srl);
		}

		// 합격시 포인트/그룹 설정 세팅 (v0.5 추가)
		$args->exam_pass_point_min = preg_replace("/[^0-9]/","",$args->exam_pass_point_min);
		$args->exam_pass_point_max = preg_replace("/[^0-9]/","",$args->exam_pass_point_max);
		$args->exam_pass_point_minus = ($args->exam_pass_point_minus=='Y')? 'Y' : 'N';

		$new_pass_group_list = array();
		$pass_group_list = $args->exam_pass_group_list;
		if($pass_group_list)
		{
			// 실제 그룹이 존재하는지 체크
			$oMemberModel = getModel('member');
			$group_list = $oMemberModel->getGroups();
			for($i=0;$i<count($pass_group_list);$i++)
			{
				if(!$group_list[$pass_group_list[$i]]) continue;
				$new_pass_group_list[] = $pass_group_list[$i];
			}
		}
		if($new_pass_group_list) $args->exam_pass_group_list = implode(",",$new_pass_group_list);
		else $args->exam_pass_group_list = '';

		// insert/update the board module based on module_srl
		if(!$args->module_srl) {
			$args->hide_category = 'N';
			$output = $oModuleController->insertModule($args);
			$msg_code = 'success_registed';
		} else {
			$args->hide_category = $module_info->hide_category;
			$output = $oModuleController->updateModule($args);
			$msg_code = 'success_updated';
		}

		if(!$output->toBool()) return $output;

		// setup list config
		$list = explode(',',Context::get('list'));
		if(count($list))
		{
			$list_arr = array();
			foreach($list as $val)
			{
				$val = trim($val);
				if(!$val) continue;
				$list_arr[] = $val;
			}
			$config = new StdClass();
			$config->list_config = $list_arr;
			$oModuleController = getController('module');
			$oModuleController->insertModulePartConfig('exam', $output->get('module_srl'), $config);
		}

		$this->setMessage($msg_code);
		if (Context::get('success_return_url')){
			changeValueInUrl('mid', $args->mid, $module_info->mid);
			$this->setRedirectUrl(Context::get('success_return_url'));
		}else{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispExamAdminInsert', 'module_srl', $output->get('module_srl')));
		}
	}
    /**
	 * @brief 시험 모듈 삭제
	 **/
	public function procExamAdminDeleteModule()
	{
		$module_srl = Context::get('module_srl');
		if(!$module_srl) return $this->makeObject(-1, 'msg_invalid_request');

		$oModuleController = getController('module');
		$output = $oModuleController->deleteModule($module_srl);
		$output = $this->deleteModuleAfter($module_srl);

		// Call a trigger (after)

		// Call a trigger (after)
		if(!$output->toBool()) return $output;

		$obj = new StdClass();
		$obj->module_srl = $module_srl;
		$trigger_output = ModuleHandler::triggerCall('exam.deleteModule', 'after', $obj);
		if(!$trigger_output->toBool())
		{
			return $trigger_output;
		}

		$this->add('module','exam');
		$this->add('page',Context::get('page'));
		$this->setMessage('success_deleted');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispExamAdminList');
		$this->setRedirectUrl($returnUrl);
    }

    /**
	 * @brief 분류 설정 저장
	 **/
	public function procExamAdminSaveCategorySettings()
	{
		$module_srl = Context::get('module_srl');
		$mid = Context::get('mid');

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		if($module_info->mid != $mid)
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}

		$module_info->hide_category = Context::get('hide_category') == 'Y' ? 'Y' : 'N';
		$oModuleController = getController('module');
		$output = $oModuleController->updateModule($module_info);
		if(!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_updated');
		if (Context::get('success_return_url'))
		{
			$this->setRedirectUrl(Context::get('success_return_url'));
		}
		else
		{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispExamAdminCategoryList', 'module_srl', $output->get('module_srl')));
		}
	}
	/**
	 * @brief 시험응시현황에서 선택한 기록에 대해 (상태변경||삭제)
	 **/
	public function procExamAdminSelectedResultManage()
	{
		$var = Context::getRequestVars();
		$module_srl = $var->module_srl;
		$status = $var->status;
		$logs = $var->log_srls;

		$oDB = &DB::getInstance();
		$oDB->begin();

		$oExamModel = getModel('exam');
		$oExamController = getController('exam');
		foreach($logs as $key=>$log_srl)
		{
			// 기록이 존재하는지 체크
			$resultitem = $oExamModel->getExamResult($log_srl);
			if(!$resultitem->log_srl) continue;
			$args = new stdClass();
			$args->log_srl = $log_srl;
			switch($var->type)
			{
				case 'modify':
					{
						if(isset(Context::getLang('resultStatusList')->{$status}))
						{
							$args->status = $var->status;
							$output = executeQuery('exam.updateResultStatus', $args);
							if(!$output->toBool())
							{
								$oDB->rollback();
								return $output;
							}
						}
						$this->setMessage('success_updated');
						break;
					}
				case 'delete':
					{
						$args->document_srl = $resultitem->document_srl;
						$output = $oExamController->deleteResult($args);
						if(!$output->toBool())
						{
							$oDB->rollback();
							return $output;
						}
						$this->setMessage('success_deleted');
					}
			}
		}

		// commit
		$oDB->commit();

		// Send a message
		$message = $var->message;
		if($message)
		{
			$oCommunicationController = getController('communication');

			$logged_info = Context::get('logged_info');
			$title = cut_str($message,10,'...');
			$sender_member_srl = $logged_info->member_srl;
			foreach($members as $member_srl)
			{
				$oCommunicationController->sendMessage($sender_member_srl, $member_srl, $title, $message, false);
			}
		}
		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispExamAdminResultList','module_srl',$module_srl);
		$this->setRedirectUrl($returnUrl);
	}
    /**
	 * @brief 응시현황 - 기록 수정
	 **/
    public function procExamAdminUpdateResult()
	{
		$oExamController = getController('exam');
		$oExamModel = getModel('exam');

		$args = Context::getRequestVars();
		if(!$args->log_srl) return $this->makeObject(-1, 'msg_not_founded');
		if($args->score > 100) return $this->makeObject(-1, 'msg_invalid_exam_score');
		if(!isset(Context::getLang('resultStatusList')->{$args->status})) unset($args->status);

		// 기록 구해옴
		$resultitem = $oExamModel->getExamResult($args->log_srl);
		if(!$resultitem->log_srl) return $this->makeObject(-1,'msg_not_founded');

		$new_args = new StdClass();
		$new_args->log_srl = $resultitem->log_srl;
		$new_args->correct_count = preg_replace("/[^0-9]/","",$args->correct_count);
		$new_args->wrong_count = preg_replace("/[^0-9]/","",$args->wrong_count);
		$new_args->score = preg_replace("/[^0-9]/","",$args->score);
		$new_args->exam_time = preg_replace("/[^0-9]/","",$args->exam_time);
		$new_args->status = $args->status;

		$output = $oExamController->updateResult($new_args);
		if(!$output->toBool()) return $output;

		$this->setMessage('success_updated');
		if (Context::get('success_return_url')){
			$this->setRedirectUrl(Context::get('success_return_url'));
		}else{
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispExamAdminResultList', 'module_srl', $args->module_srl));
		}
	}
	/**
	 * @brief 모듈이 삭제되면, 해당 모듈의 시험지/참여정보/ 등을 삭제
	 **/
	public function deleteModuleAfter($module_srl)
	{
		if(!$module_srl) return $this->makeObject(-1, 'msg_invalid_request');

		$args = new StdClass();
		$args->module_srl = $module_srl;
		$output = executeQuery('exam.deleteExamByModuleSrl', $args);
		if(!$output->toBool()) return $output;
		$output = executeQuery('exam.deleteExamQuestionByModuleSrl', $args);
		if(!$output->toBool()) return $output;
		$output = executeQuery('exam.deleteExamResultByModuleSrl', $args);

		return $output;
	}
}
/* End of file exam.admin.controller.php */
/* Location: ./modules/exam/exam.admin.controller.php */
