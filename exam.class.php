<?php
require_once(_XE_PATH_.'modules/exam/exam.item.php');
require_once(_XE_PATH_.'modules/exam/exam.question.item.php');

/**
 * @class  exam
 * @author 러키군 (admin@barch.kr)
 * @brief high class of the module page
 */
class exam extends ModuleObject
{
	var $search_option = array('exam_title','user_name','nick_name'); /// 검색 옵션
	var $order_target = array('join_count', 'regdate', 'last_update'); // 정렬 옵션
	var $skin = "default"; ///< skin name
	var $list_count = 20; ///< the number of documents displayed in a page
	var $page_count = 10; ///< page number
	var $category_list = NULL; ///< category list

    // 모듈 트리거 목록
	private $triggers = array(
		array(
			'name' => 'menu.getModuleListInSitemap',
			'module' => 'exam',
			'type' => 'model',
			'func' => 'triggerModuleListInSitemap',
			'position' => 'after'
		),
		array(
			'name' => 'member.deleteMember',
			'module' => 'exam',
			'type' => 'controller',
			'func' => 'triggerDeleteMember',
			'position' => 'after'
		)
	);

    /**
     * @brief 모듈 설치 시 호출.
	 */
	function moduleInstall()
	{
		// 트리거 추가
        $oModuleController = getController('module');
		foreach($this->triggers as $trigger)
		{
			$oModuleController->insertTrigger($trigger['name'],$trigger['module'],$trigger['type'],$trigger['func'],$trigger['position']);
		}

        // exam generated from the cache directory to use
		FileHandler::makeDir('./files/cache/exam');

		return new Object();
	}

	/**
	 * @brief  업데이트 체크를 위해 호출.
	 */
	function checkUpdate()
	{
		$oDB = &DB::getInstance();

		// 복수정답 처리옵션 필드 추가(v0.8 추가)
		if(!$oDB->isColumnExists("exam_question", "answer_check_type")) return true;

		// 합격시 지급할 포인트 필드 추가(v0.5 추가)
		if(!$oDB->isColumnExists("exam", "pass_point")) return true;
		if(!$oDB->isColumnExists("exam", "pass_group_list")) return true;

		// 트리거 확인해서 추가안된 항목이 있으면 true 리턴
		$oModuleModel = getModel('module');
        foreach($this->triggers as $trigger)
		{
			$res = $oModuleModel->getTrigger($trigger['name'],$trigger['module'],$trigger['type'],$trigger['func'],$trigger['position']);
			if (!$res)
			{
				return true;
			}
		}
        return false;
	}

	/**
	 * @brief 모듈 업데이트 시 호출.
	 */
	function moduleUpdate()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');

		$oDB = &DB::getInstance();

		// 합격시 지급할 포인트 필드 추가(v0.5 추가)
		if(!$oDB->isColumnExists("exam", "pass_point"))
		{
			$oDB->addColumn("exam", "pass_point", "number", 11, 0, true);
		}
		if(!$oDB->isColumnExists("exam", "pass_group_list"))
		{
			$oDB->addColumn("exam", "pass_group_list", "varchar", 80, "", true);
			/**
			 * v0.5부터 result테이블의 answer 기록이 변경되었으므로 업그레이드 사용자일경우 데이터 변환을 시켜준다.
			 **/
			$output = executeQuery('exam.getResultListByAll');
			if($output->data)
			{
				$oExamModel = getModel('exam');
				foreach($output->data as $key => $val)
				{
					$examitem = $oExamModel->getExam($val->document_srl);
					$old_answer = unserialize($val->answer);
					$new_answer = array();
					foreach($examitem->getQuestions() as $no => $qitem)
					{
						$qitem->add('my_answer',$old_answer[$no]);
						$new_answer[$no] = $qitem;
					}
					$val->answer = serialize($new_answer);
					$_output = executeQuery('exam.updateResultConverter', $val);
				}
			}
		}
		// 복수정답 처리옵션 필드 추가(v0.8 추가)
		if(!$oDB->isColumnExists("exam_question", "answer_check_type"))
		{
			$oDB->addColumn("exam_question", "answer_check_type", "number", 1, 0, true);
			/**
			 * v0.8에서 result테이블의 answer 기록이 변경되었으므로 업그레이드 사용자일경우 데이터 변환을 시켜준다.
			 **/
			$output = executeQuery('exam.getResultListByAll');
			if($output->data)
			{
				foreach($output->data as $key => $val)
				{
					$score = ceil(1 / ($val->correct_count+$val->wrong_count) * 100);
					$answer = unserialize($val->answer);
					foreach($answer as $no => $qitem)
					{
						$check = ($qitem->get('my_answer')==$qitem->getAnswer())? "O" : "X";

						$qitem->add('score',$_score);
						$qitem->add('my_answer_result',$check);
						$answer[$no] = $qitem;
					}
					$val->answer = serialize($answer);
					$_output = executeQuery('exam.updateResultConverter', $val);
				}
			}
		}

        // 트리거 확인 및 추가
		foreach($this->triggers as $trigger)
		{
			$res = $oModuleModel->getTrigger($trigger['name'],$trigger['module'],$trigger['type'],$trigger['func'],$trigger['position']);
			if (!$res)
			{
				$oModuleController->insertTrigger($trigger['name'],$trigger['module'],$trigger['type'],$trigger['func'],$trigger['position']);
			}
		}
        return new Object(0,'success_updated');
	}

	/**
	 * @brief 캐시파일 재생성시 호출됨.
	 */
	function recompileCache()
	{
	}

	/**
	 * @brief 쉬운 설치를 통한 모듈 삭제 시 호출된다.
	 */
	public function moduleUninstall()
	{
		// 트리거 제거
		$oModuleController = getController('module');
        foreach($this->triggers as $trigger)
		{
			$res = $oModuleModel->deleteTrigger($trigger['name'],$trigger['module'],$trigger['type'],$trigger['func'],$trigger['position']);
		}
		
		return new Object();
	}
	/**
	 * Exam Status List
	 * @return array
	 */
	function getStatusList()
	{
		$statusList = Context::getLang('statusList');
		return $statusList;
	}
	function getResultStatusList()
	{
		$statusList = Context::getLang('resultStatusList');
		return $statusList;
	}
	function getConfigStatus($key='',$key_return=false) {
		$list = $this->getStatusList();
		if($key && array_key_exists(strtolower($key), $list)) return ($key_return)? $key : $list[$key];
		else return ($key_return)? 'Y' : $list['Y'];
	}
	function getConfigPageType($key=0,$key_return=false) {
		$list = Context::getLang('pageTypeList');
		if(array_key_exists(strtolower($key), $list)) return ($key_return)? $key : $list[$key];
		else return ($key_return)? 0 : $list[0];
	}
	function getConfigResultType($key=0,$key_return=false) {
		$list = Context::getLang('resultTypeList');
		if(array_key_exists(strtolower($key), $list)) return ($key_return)? $key : $list[$key];
		else return ($key_return)? 0 : $list[0];
	}
}
/* End of file exam.class.php */
/* Location: ./modules/exam/exam.class.php */
