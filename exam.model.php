<?php
/**
 * @class  examModel
 * @author 러키군 (admin@barch.kr)
 * @brief Model class of the exam module
 */
class examModel extends exam
{
    /**
     * @brief 메뉴 편집의 메뉴 추가에 모듈이 나올 수 있도록 추가
	 */
	public function triggerModuleListInSitemap(&$arr)
	{
		array_push($arr, 'exam');
	}
	/**
	 * @brief 목록설정에서 사용할 기본리스트 구해옴
	 **/
	public function getDefaultListConfig($module_srl)
	{
		// add virtual srl, title, registered date, update date, nickname, ID, name, readed count, voted count etc.
		$virtual_vars = array( 'exam_srl','exam_title','exam_content','category','user_name','nick_name','exam_join_point','exam_pass_point','exam_date','regdate','last_update','exam_question_count','exam_join_count');
		$extra_vars = array();
		foreach($virtual_vars as $key)
		{
			$extra_vars[$key] = new ExtraItem($module_srl, -1, in_array($key,'user_name','nick_name') ? Context::getLang('exam_writer') . " " . Context::getLang($key) : Context::getLang($key), $key, 'N', 'N', 'N', null);
		}
		return $extra_vars;
	}
	/**
	 * @brief 목록설정에서 사용할 리스트 구해옴
	 **/
	public function getListConfig($module_srl)
	{
		$oModuleModel = getModel('module');

		// get the list config value, if it is not exitsted then setup the default value
		$module_config = $oModuleModel->getModulePartConfig('exam', $module_srl);
        $list_config = $module_config->list_config;
		if(!$list_config || count($list_config) <= 0)
		{
			$list_config = array('exam_srl','exam_title', 'exam_date', 'exam_question_count','exam_join_count');
		}

		$output = array();
		foreach($list_config as $key)
        {
			$output[$key] = new ExtraItem($module_srl, -1, Context::getLang($key), $key, 'N', 'N', 'N', null);
        }
		return $output;
	}
	/**
	 * 특정 시험정보를 구해옴
	 * @param int $document_srl
	 * @param array $columnList
	 * @return examitem
	 */
	function getExam($document_srl=0, $columnList = array())
	{
		if(!$document_srl) return new examItem();

		if(!$GLOBALS['XE_EXAM_LIST'][$document_srl])
		{
			$examitem = new examItem($document_srl, $columnList);
			$GLOBALS['XE_EXAM_LIST'][$document_srl] = $examitem;
		}
		return $GLOBALS['XE_EXAM_LIST'][$document_srl];
	}
	/**
	 * @brief 시험 목록을 구해오는 함수
	 * @param object $obj
	 * @param array $columnList
	 * @return Object
	 */
	function getExamList($obj, $columnList = array())
	{
		if(!in_array($obj->sort_index, $this->order_target))
		{
			$obj->sort_index = 'list_order';
		}
		$this->_setSearchOption($obj, $args, $query_id, $use_division);
		$output = executeQueryArray($query_id, $args, $columnList);

		// Return if no result or an error occurs
		if(!$output->toBool()||!count($output->data)) return $output;
		$idx = 0;
		$data = $output->data;
		unset($output->data);
		if(!isset($virtual_number))
		{
			$keys = array_keys($data);
			$virtual_number = $keys[0];
		}
		foreach($data as $key => $attribute)
		{
			$document_srl = $attribute->document_srl;
			if(!$GLOBALS['XE_EXAM_LIST'][$document_srl])
			{
				$examitem = null;
				$examitem = new examItem();
				$examitem->setAttribute($attribute);
				if($is_admin) $examitem->setGrant();
				$GLOBALS['XE_EXAM_LIST'][$document_srl] = $examitem;
			}

			$output->data[$virtual_number] = $GLOBALS['XE_EXAM_LIST'][$document_srl];
			$virtual_number--;
		}
		if(count($output->data))
		{
			foreach($output->data as $number => $document)
			{
				$output->data[$number] = $GLOBALS['XE_EXAM_LIST'][$document->document_srl];
			}
		}
		return $output;
	}
	/**
	 * @brief 시험 목록의 검색 옵션을 Setting함
	 * @param object $searchOpt
	 * @param object $args
	 * @param string $query_id
	 * @param bool $use_division
	 * @return void
	 */
	function _setSearchOption($searchOpt, &$args, &$query_id, &$use_division)
	{
		// Variable check
		$args = new stdClass();
		$args->category_srl = $searchOpt->category_srl?$searchOpt->category_srl:null;
		$args->order_type = $searchOpt->order_type;
		$args->page = $searchOpt->page?$searchOpt->page:1;
		$args->list_count = $searchOpt->list_count?$searchOpt->list_count:20;
		$args->page_count = $searchOpt->page_count?$searchOpt->page_count:10;
		$args->member_srl = $searchOpt->member_srl;

		$logged_info = Context::get('logged_info');

		$args->sort_index = $searchOpt->sort_index;
		
		// Check the target and sequence alignment
		$orderType = array('desc' => 1, 'asc' => 1);
		if(!isset($orderType[$args->order_type])) $args->order_type = 'asc';

		// If that came across mid module_srl instead of a direct module_srl guhaejum
		if($searchOpt->mid)
		{
			$oModuleModel = getModel('module');
			$args->module_srl = $oModuleModel->getModuleSrlByMid($obj->mid);
			unset($searchOpt->mid);
		}

		// Module_srl passed the array may be a check whether the array
		if(is_array($searchOpt->module_srl)) $args->module_srl = implode(',', $searchOpt->module_srl);
		else $args->module_srl = $searchOpt->module_srl;

		// Category is selected, further sub-categories until all conditions
		if($args->category_srl)
		{
			$oDocumentModel = getModel('document');
			$category_list = $oDocumentModel->getCategoryList($args->module_srl);
			$category_info = $category_list[$args->category_srl];
			$category_info->childs[] = $args->category_srl;
			$args->category_srl = implode(',',$category_info->childs);
		}

		// Used to specify the default query id (based on several search options to query id modified)
		$query_id = 'exam.getExamList';

		// If the search by specifying the document division naeyonggeomsaekil processed for
		$use_division = false;

		// Search options
		$search_target = $searchOpt->search_target;
		$search_keyword = $searchOpt->search_keyword;
		if($search_target && $search_keyword)
		{
			switch($search_target)
			{
				case 'exam_title' :
					if($search_keyword) $search_keyword = str_replace(' ','%',$search_keyword);
					$args->title = $search_keyword;
					$use_division = true;
					break;
				case 'user_name' :
				case 'nick_name' :
					if($search_keyword) $search_keyword = str_replace(' ','%',$search_keyword);
					$args->{$search_target} = $search_keyword;
					break;
				case 'ipaddress' :
					$args->{$search_target} = $search_keyword;
					break;
				default :
					break;
			}
		}
	}
	/**
	 * 특정 문제의 정보를 구함
	 * @param int $question_srl
	 * @return questionitem
	 */
	function getQuestion($question_srl=0, $columnList = array())
	{
		if(!$question_srl) return new questionItem();

		$questionitem = new questionitem($question_srl, $columnList);
		return $questionitem;
	}
	/**
	 * 해당 시험에 속한 문제의 개수를 구함
	 * @param int $document_srl
	 * @return int
	 */
	function getQuestionCount($document_srl)
	{
		$args = new stdClass;
		$args->document_srl = $document_srl;
		$output = executeQuery('exam.getQuestionCount', $args);
		return (int)$output->data->count;
	}
	/**
	 * 해당 시험의 응시자 수를 구함
	 * @param int $document_srl
	 * @return int
	 */
	function getJoinCount($document_srl)
	{
		$args = new stdClass;
		$args->document_srl = $document_srl;
		$output = executeQuery('exam.getJoinCount', $args);
		return (int)$output->data->count;
	}
	/**
	 * 카테고리에 속한 시험의 개수를 구함
	 * @param int $module_srl
	 * @param int $category_srl
	 * @return int
	 */
	function getCategoryExamCount($module_srl, $category_srl)
	{
		$args = new stdClass;
		$args->module_srl = $module_srl;
		$args->category_srl = $category_srl;
		$output = executeQuery('exam.getCategoryExamCount', $args);
		return (int)$output->data->count;
	}
	/**
	 * 포인트 모듈의 설정 구해옴,
	 * @return object
	 */
	function getPointConfig()
	{
		$oModuleModel = getModel('module');
		$config = $oModuleModel->getModuleConfig('point');
		return $config;
	}
	/**
	 * 시험에 속한 문제 목록을 구함.
	 * @param int $document_srl
	 * @return object
	 */
	function getQuestionList($document_srl)
	{
		if(!isset($document_srl))
		{
			return;
		}

		// 해당 시험정보 먼저 구해옴
		$columnList = array('document_srl', 'module_srl', 'question_count','pass_point','pass_group_list');
		$examitem = $this->getExam($document_srl, $columnList);

		// return if no doc exists.
		if(!$examitem->isExists())
		{
			return;
		}

		// return if no question_count exists
		if($examitem->getQuestionCount() < 1)
		{
			return;
		}

		$module_srl = $examitem->get('module_srl');

		// get a list of questions
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$output = executeQueryArray('exam.getQuestionList', $args);

		// return if an error occurs in the query results
		if(!$output->toBool())
		{
			return;
		}

		return $output;
	}
	/**
	 *	시험결과 기록을 구해옴.
	 * @return object
	 */
	function getExamResult($log_srl=0)
	{
		if(!$log_srl) return;
		$args = new StdClass();
		$args->log_srl = $log_srl;
		$output = executeQueryArray('exam.getResult', $args);
		if(!$output->toBool())
		{
			return;
		}
		if($output->data[0])
		{
			$output->data[0]->answer = unserialize($output->data[0]->answer);
		}
		return $output->data[0];
	}
	/**
	 *	시험의  응시현황을 구해옴 (문서번호+회원번호)
	 * @return object
	 */
	function getExamResultByDocumentSrl($document_srl,$member_srl=0)
	{
		if(!$document_srl) return;
		$args = new StdClass();
		$args->document_srl = $document_srl;
		if($member_srl) $args->member_srl = $member_srl;

		$output = executeQueryArray('exam.getResult', $args);
		if(!$output->toBool())
		{
			return;
		}
		if($output->data[0])
		{
			$output->data[0]->answer = unserialize($output->data[0]->answer);
		}
		return $output->data[0];
	}
	/**
	 *	나의 시험 응시현황 목록을 구해옴.
	 * @return object
	 */
	function getExamResultList($obj, $columnList = array())
	{
		$logged_info = Context::get('logged_info');
		if($obj->status)
		{
			$statusList = $this->getResultStatusList();
			if(!array_key_exists(strtoupper($obj->status),$statusList)) unset($obj->status);
		}
		$args = new StdClass();
		$args->list_count = ($obj->list_count)? $obj->list_count : 20;
		$args->page_count = ($obj->page_count)? $obj->page_count : 10;
		$args->page = ($obj->page)? $obj->page : 1;
		$args->sort_index = 'regdate';
		$args->sort_order = 'desc';
		$args->member_srl = $obj->member_srl;
		$args->module_srl = $obj->module_srl;
		$args->status = $obj->status;

		if($obj->mid)
		{
			$oModuleModel = getModel('module');
			$args->module_srl = $oModuleModel->getModuleSrlByMid($obj->mid);
		}

		$output = executeQueryArray('exam.getResultList', $args);
		if(!$output->toBool())
		{
			return;
		}
		if($output->data)
		{
			foreach($output->data as $key => $val)
			{
				$output->data[$key]->answer = unserialize($val->answer);
			}
		}

		return $output;
	}
	/**
	 *	시간 (초)를 X시간 X분 X초 형태의 텍스트로 변환하는 함수.
	 * @param seconds
	 * @return string
	 */
	function getTimeText($time)
	{
		$fixStr = array(Context::getLang('unit_year'), Context::getLang('unit_day'), Context::getLang('unit_hours'), Context::getLang('unit_min'), Context::getLang('unit_sec'));
		$fixStep = array(0, 365, 24, 60, 60);
		$putStr = ""; 
		$num = count($fixStr) -1; 
		for($i = $num; $i > 0; $i --)
		{
			$tmp[$i] = $time % $fixStep[$i];
			$time = intval($time / $fixStep[$i]);
		} 
		$tmp[0] = $time;
		$flag = false;
		for($i = 0; $i <= $num; $i ++) {
			if($tmp[$i] || $flag) {
				$putStr .= ($tmp[$i])? $tmp[$i].$fixStr[$i].' ' : "";
				$flag = true;
			}
		}
		if(!$putStr) $putStr = '0'.$fixStr[4];
		return $putStr;
	}

}
/* End of file exam.model.php */
/* Location: ./modules/exam/exam.model.php */
