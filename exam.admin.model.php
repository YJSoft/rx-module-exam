<?php
/**
 * @class  examAdminModel
 * @author 러키군 (admin@barch.kr)
 * @brief  exam module admin model class
 */
class examAdminModel extends exam
{
	function init()
	{
	}
	/**
	 * @brief 관리자페이지 -> 시험응시 현황 구해옴
	 **/
	function getResultList()
	{
		// Search option
		$args = new stdClass();
		$args->module_srl = Context::get('module_srl');
		$args->status = (Context::get('status') && isset(Context::getLang('resultStatusList')->{Context::get('status')}))? Context::get('status') : '';
		$search_target = trim(Context::get('search_target'));
		$search_target2 = trim(Context::get('search_target2'));
		$search_target3 = trim(Context::get('search_target3'));
		$search_keyword = trim(Context::get('search_keyword'));
		$search_keyword2 = trim(Context::get('search_keyword2'));
		$search_keyword3 = trim(Context::get('search_keyword3'));

		$oMemberModel = getModel('member');
		if($search_target && $search_keyword)
		{
			switch($search_target)
			{
				case 'document_srl' :
					$args->document_srl = preg_replace("/[^0-9]/","",$search_keyword);
					break;
				case 'member_srl' :
					$args->member_srl = preg_replace("/[^0-9]/","",$search_keyword);
					break;
				case 'correct_count' :
					$args->correct_count = preg_replace("/[^0-9]/","",$search_keyword);
					break;
				case 'correct_count_more' :
					$args->correct_count_more = preg_replace("/[^0-9]/","",$search_keyword);
					break;
				case 'correct_count_less' :
					$args->correct_count_less = preg_replace("/[^0-9]/","",$search_keyword);
					break;
				case 'score' :
					$args->score = preg_replace("/[^0-9]/","",$search_keyword);
					break;
				case 'score_more' :
					$args->score_more = preg_replace("/[^0-9]/","",$search_keyword);
					break;
				case 'score_less' :
					$args->score_more = preg_replace("/[^0-9]/","",$search_keyword);
					break;
				case 'regdate_more' :
					$args->regdate_more = preg_replace("/[^0-9]/","",$search_keyword);
					break;
				case 'regdate_less' :
					$args->regdate_less = preg_replace("/[^0-9]/","",$search_keyword);
					break;
			}
		}
		if($search_target2 && $search_keyword2)
		{
			switch($search_target2)
			{
				case 'document_srl' :
					$args->document_srl = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
				case 'member_srl' :
					$args->member_srl = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
				case 'correct_count' :
					$args->correct_count = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
				case 'correct_count_more' :
					$args->correct_count_more = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
				case 'correct_count_less' :
					$args->correct_count_less = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
				case 'score' :
					$args->score = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
				case 'score_more' :
					$args->score_more = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
				case 'score_less' :
					$args->score_more = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
				case 'regdate_more' :
					$args->regdate_more = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
				case 'regdate_less' :
					$args->regdate_less = preg_replace("/[^0-9]/","",$search_keyword2);
					break;
			}
		}
		if($search_target3 && $search_keyword3)
		{
			switch($search_target3)
			{
				case 'document_srl' :
					$args->document_srl = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
				case 'member_srl' :
					$args->member_srl = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
				case 'correct_count' :
					$args->correct_count = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
				case 'correct_count_more' :
					$args->correct_count_more = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
				case 'correct_count_less' :
					$args->correct_count_less = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
				case 'score' :
					$args->score = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
				case 'score_more' :
					$args->score_more = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
				case 'score_less' :
					$args->score_more = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
				case 'regdate_more' :
					$args->regdate_more = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
				case 'regdate_less' :
					$args->regdate_less = preg_replace("/[^0-9]/","",$search_keyword3);
					break;
			}
		}

		// Change the query id if selected_group_srl exists (for table join)
		$sort_order = Context::get('sort_order');
		$sort_index = Context::get('sort_index');
		if(!in_array($sort_index,array('question_count','correct_count','score')))
		{
			$sort_index = "log_srl";
		}
		if($sort_order != 'asc')
		{
			$sort_order = 'desc';
		}

		$args->sort_index = $sort_index; 
		$args->sort_order = $sort_order;
		Context::set('sort_index', $sort_index);
		Context::set('sort_order', $sort_order);

		// Other variables
		$args->page = Context::get('page');
		$args->list_count = 30;
		$args->page_count = 10;
		$output = executeQuery('exam.getResultList', $args);

		// 데이터가 있으면 사용하게끔 세팅
		if($output->data)
		{
			$oMemberModel = getModel('member');
			$oExamModel = getModel('exam');
			foreach($output->data as $key => $val)
			{
				// 회원의 이름/닉네임 정보 구해옴
				$member_info = $oMemberModel->getMemberInfoByMemberSrl($val->member_srl);
				if($member_info->member_srl)
				{
					$val->nick_name = $member_info->nick_name;
					$val->user_name = $member_info->user_name;
				}
				$val->exam_timeText = $oExamModel->getTimeText($val->exam_time);
				$output->data[$key]->answer = unserialize($val->answer);
			}
		}
		return $output;
	}
}
/* End of file exam.admin.model.php */
/* Location: ./modules/exam/exam.admin.model.php */
