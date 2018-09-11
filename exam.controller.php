<?php
/**
 * @class  examController
 * @author 러키군 (admin@barch.kr)
 * @brief  exam module Controller class
 */
class examController extends exam
{
	function init()
	{
	}
	/**
	 * @brief 시험지 생성을 처리하는 함수
	 */
	public function procExamCreate()
	{
		// 권한 체크
		if($this->module_info->module != "exam")
		{
			return $this->makeObject(-1, "msg_invalid_request");
		}
		if (!$this->grant->create)
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}

		$logged_info = Context::get('logged_info');
		$args = Context::getRequestVars();
		if(!$args->exam_title) return $this->makeObject(-1, 'msg_not_exam_title');
		$args->title = $args->exam_title;
		$args->cutline = (int)$args->exam_cutline;
		$args->join_point = (int)$args->exam_join_point;
		$args->content = $args->exam_content;

		// 합격시 그룹변경 사용시 입력값 체크..
		if($this->module_info->exam_pass_group_option && $args->exam_pass_group_list)
		{
			$group_list = array();
			if($this->module_info->exam_pass_group_option>1)
			{
				$default_group_list = explode(",", $this->module_info->exam_pass_group_list);
				if(!count($default_group_list)) unset($args->exam_pass_group_list);
				for($i=0;$i<count($args->exam_pass_group_list);$i++)
				{
					if(!in_array($args->exam_pass_group_list[$i],$default_group_list)) continue;
					$group_list[] = $args->exam_pass_group_list[$i];
				}
			} else {
				$default_group_list = $logged_info->group_list;
				if(!count($default_group_list)) unset($args->exam_pass_group_list);
				for($i=0;$i<count($args->exam_pass_group_list);$i++)
				{
					if(!array_key_exists($args->exam_pass_group_list[$i],$default_group_list)) continue;
					$group_list[] = $args->exam_pass_group_list[$i];
				}
			}
			$args->exam_pass_group_list = implode(",",$group_list);
		} else {
			$args->exam_pass_group_list = '';
		}
		// 합격시 포인트 사용시 입력값 체크..
		if($this->module_info->exam_pass_point_option && $args->exam_pass_point)
		{
			if($this->module_info->exam_pass_point_option>1)
			{
				// 관리자가 설정한 범위사이가 아니면 0 처리
				$pass_point_min = (int)$this->module_info->exam_pass_point_min;
				$pass_point_max = (int)$this->module_info->exam_pass_point_max;
			} else {
				// 자신의 포인트만큼 설정가능
				$oPointModel = getModel('point');
				$pass_point_min = 0;
				$pass_point_max = $oPointModel->getPoint($logged_info->member_srl);
			}
			if($pass_point_min > $args->exam_pass_point || $args->exam_pass_point > $pass_point_max) $args->exam_pass_point = 0;
		} else {
			$args->exam_pass_point =0;
		}
		$args->pass_group_list = $args->exam_pass_group_list;
		$args->pass_point = $args->exam_pass_point;

		// 불필요한 변수 제거
		unset($args->exam_title);
		unset($args->exam_cutline);
		unset($args->exam_content);
		unset($args->exam_join_point);
		unset($args->exam_pass_group_list);
		unset($args->exam_pass_point);

		$args->module_srl = $this->module_srl;
		$args->page_type = $this->getConfigPageType($args->page_type, true);
		$args->result_type = $this->getConfigResultType($args->result_type, true);
		$args->is_date = ($args->is_date=="Y")? "Y" : "N";
		$args->is_time = ($args->is_time=="Y")? "Y" : "N";

		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($args->document_srl);
		$is_update = FALSE;
		if($examitem->isExists() && $examitem->document_srl==$args->document_srl)
		{
			$is_update = TRUE;
		}

		// update
		if($is_update)
		{
			$output = $this->updateExam($examitem, $args);
		} else { // insert
			$output = $this->insertExam($args);
		}

		// if there is an error
		if(!$output->toBool())
		{
			return $output;
		}

		// 성공 시 시험 목록으로 이동
		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid);
		$this->setRedirectUrl($returnUrl);
	}
	/**
	 * @brief 시험지 삭제를 처리하는 함수
	 */
	public function procExamDelete()
	{
		// 권한 체크
		if($this->module_info->module != "exam")
		{
			return $this->makeObject(-1, "msg_invalid_request");
		}
		if (!$this->grant->create)
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}

		$module_srl = Context::get('module_srl');
		$document_srl = Context::get('document_srl');

		// 삭제할 시험지 정보 구해옴
		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($document_srl);

		// 해당 시험지가 존재하는지 체크
		if(!$examitem->isExists())
		{
			return $this->makeObject(-1,'msg_not_examitem');
		}

		// 해당 시험지에 대한 권한 체크
		if(!$examitem->isGranted())
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}
		if($examitem->get('module_srl')!=$module_srl)
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}

		$output = $this->deleteExam($document_srl,$module_srl);
		if(!$output->toBool())
		{
			return $output;
		}

		// 카테고리 개수 조정
		$this->updateCategoryCount($module_srl, $examitem->get('category_srl'));

		// 성공 시 시험 목록으로 이동
		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid);
		$this->setRedirectUrl($returnUrl);
	}
	/**
	 * @brief 시험지에 문제 출제/수정 처리
	 */
	public function procExamQuestionInsert()
	{
		// 권한 체크
		if($this->module_info->module != "exam")
		{
			return $this->makeObject(-1, "msg_invalid_request");
		}
		if (!$this->grant->create)
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}
		$oExamModel = getModel('exam');

		// 시험지 정볼르 구해와서, 문제 출제 궈난이 있는지 체크
		$document_srl = Context::get('document_srl');
		$question_srl = Context::get('question_srl');

		$examitem = $oExamModel->getExam($document_srl);
		if(!$examitem->isExists()) return $this->makeObject(-1, 'msg_not_founded');
		if(!$examitem->isGranted()) return $this->makeObject(-1, 'msg_not_permitted');

		// question_srl이 있으면 문제정보도 체크
		$questionitem = $oExamModel->getQuestion($question_srl);
		// srl값이 있고 해당 문제가 존재하면 수정처리, 아니면 신규처리
		if($question_srl && $questionitem->isExists())
		{
			$is_mode = 'update';
		} else {
			$is_mode = 'insert';
		}

		//  var setting
		$params = Context::getRequestVars();

		// 정답이 하나도 입력안되있을경우..
		if($params->q_type==1)
		{
			if(!$params->q_answer6) return $this->makeObject(-1, 'msg_not_answer');
			$params->q_answer = $params->q_answer6;
		} else {
			// 1~5번중에 하나도 입력 안했으면 에러..!
			$is_answer = FALSE;
			for($i=1;$i<=5;$i++)
			{
				if($params->{'q_answer'.$i})
				{
					$is_answer = TRUE;
					break;
				}
			}
			if(!$is_answer) return $this->makeObject(-1, 'msg_not_answer');

			// 선택된 정답의 보기가 없으면 답 취소..
			if(!$params->q_answer) return $this->makeObject(-1, 'msg_not_answer');
			$ans_list = explode(",", $params->q_answer);
			$new_ans_list = array();
			foreach($ans_list as $kry=>$val)
			{
				if(!$params->{'q_answer'.$val}) continue;
				$new_ans_list[] = $val;
			}
			$params->q_answer = implode(",", $new_ans_list);
		}
		$args = new StdClass();
		$args->question_level = (int)$params->q_level;
		$args->question_type = (int)$params->q_type;
		$args->title = htmlspecialchars($params->q_title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->content = $params->q_content;
		$args->use_description = ($params->use_description=='Y')? 'Y' : 'N';
		$args->description_title = htmlspecialchars($params->description_title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
		$args->description = removeHackTag($params->q_description_content);
		$args->answer = $params->q_answer;
		$args->answer_check_type = (int)$params->answer_check_type;
		for($i=1;$i<=5;$i++)
		{
			$args->{'answer'.$i} = $params->{'q_answer'.$i};
		}

		// update
		if($is_mode=='update')
		{
			$args->question_srl = $question_srl;
			$output = $this->updateQuestion($args);
		} else { // insert
			// 신규일때 srl값이 넘어왔으면 첨부파일이 존재하는지 체크
			if($question_srl)
			{
				$oFileModel = getModel('file');
				$file_count = $oFileModel->getFilesCount($question_srl);
				if($file_count>=1) $args->question_srl = $question_srl;
			}
			$args->module_srl = $examitem->get('module_srl');
			$args->document_srl = $examitem->document_srl;
			$output = $this->insertQuestion($args);
		}

		if(!$output->toBool()) return $output;
		$this->setMessage('success_saved');
		$this->setRedirectUrl(getNotEncodedUrl('', 'mid', $this->mid,'document_srl',$examitem->document_srl,'act','dispExamEditMode'));
	}
	/**
	 * @brief 시험문제 삭제 처리
	 */
	public function procExamQuestionDelete()
	{
		if($this->module_info->module != "exam")
		{
			return $this->makeObject(-1, "msg_invalid_request");
		}
		$oExamModel = getModel('exam');

		// 시험지 정볼르 구해와서, 문제 출제 궈난이 있는지 체크
		$document_srl = Context::get('document_srl');
		$question_srl = Context::get('question_srl');

		// 권한 체크
		$examitem = $oExamModel->getExam($document_srl);
		if(!$examitem->isExists()) return $this->makeObject(-1, 'msg_not_founded');
		if(!$examitem->isGranted()) return $this->makeObject(-1, 'msg_not_permitted');

		// question_srl이 있으면 문제정보도 체크
		$questionitem = $oExamModel->getQuestion($question_srl);
		if(!$questionitem->isExists()) return $this->makeObject(-1, 'msg_not_founded 1');
		if($questionitem->get('document_srl')!=$examitem->document_srl) return $this->makeObject(-1, 'msg_invalid_request');

		$args = new StdClass();
		$args->question_srl = $question_srl;
		$args->document_srl = $document_srl;

		$output = $this->deleteQuestion($args);
		if(!$output->toBool()) return $output;
		$this->setMessage('success_deleted');
	}
	/**
	 * @brief 시험 결과를 처리하는 함수
	 */
	public function procExamJoin()
	{
		// 권한 체크
		if($this->module_info->module != "exam")
		{
			return $this->makeObject(-1, "msg_invalid_request");
		}
		if (!$this->grant->join)
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}
		if(!checkCSRF())
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}
		$args = Context::getRequestVars();
		if(!$args->document_srl || !$args->module_srl) return $this->makeObject(-1, 'msg_not_founded');

		// 응시한 시험정보 구해옴
		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($args->document_srl);
		if(!$examitem->isExists()) return $this->makeObject(-1, 'msg_not_founded');

		// 이미 응시했으면...
		$logged_info = Context::get('logged_info');
		$resultitem = $oExamModel->getExamResultByDocumentSrl($examitem->document_srl,$logged_info->member_srl);
		if($resultitem->log_srl) return $this->makeObject(-1, 'msg_exists_result');

		// 시작시 생선한 세션이 존재하는지 체크
		if(!$_SESSION['exam_joinlog'][$examitem->document_srl]) return $this->makeObject(-1, 'msg_not_session');

		// 혹시나.. 출제된 문제가 없는데 전송된경우 체크
		if(!$examitem->get('question_count')) return $this->makeObject(-1,'no_question_list');

		// 시험기간인지 체크함
		$today = date("YmdHi");
		if($examitem->isDate() && ($examitem->get('start_date') || $examitem->get('end_date')))
		{
			if($examitem->get('start_date') && zdate($examitem->get('start_date'),'YmdHi') > $today) return $this->makeObject(-1, 'msg_not_exam_date');
			if($examitem->get('end_date') && $today > zdate($examitem->get('end_date'),'YmdHi')) return $this->makeObject(-1, 'msg_not_exam_date');
		}
		// 제한시간 체크
		$start_date = $_SESSION['exam_joinlog'][$examitem->document_srl]['start_date'];
		$now = strtotime(date("YmdHis"));
		if($examitem->isTime() && $examitem->get('exam_time'))
		{
			// 끝나는시간 (시작시간+제한시간 초)
			$end_date = strtotime($start_date." +".$examitem->get('exam_time')." seconds");
			$over_time = $now-$end_date;
			if($over_time>10) return $this->makeObject(-1, 'msg_time_over'); // 처리시간으로 10초정도는 예외처리 둠
		}
		$exam_time = $now-strtotime($start_date);

		// 문제 정답 체크
		$answers = array();
		$correct_count = 0;
		$wrong_count = 0;
		$score = 0;
		$default_score = ceil(1 / $examitem->get('question_count') * 100); //문제당 기본 배점(1/총문제수*100)
		foreach($examitem->getQuestions() as $no => $qitem)
		{
			$answer = $args->{'answer'.$no}; // 응시자가 선택(입력)한 답
			$q_answer = $qitem->get('answer'); // 문제 정답
			$q_answer_count = $qitem->getAnswerCount(); // 정답 개수
			$check = 'X'; // O정답, X오답, P부분정답
			// 객관식이고 복수정답일때 처리
			if(!$qitem->getQType() && $q_answer_count>1)
			{
				$answer_list = explode(",", $answer);
				$q_answer_list = $qitem->getAnswerList();
				$answer_check_type = $qitem->get('answer_check_type');
				// 모두일치하면 정답+부분점수(기본배점/맞은갯수)
				if($answer_check_type==2)
				{
					$cnt = 0;
					foreach($q_answer_list as $key => $val)
					{
						if(in_array($val, $answer_list))
						{
							$cnt++;
						}
					}
					if($cnt) {
						if($cnt==$q_answer_count) $check = 'O';
						else $check = 'P';
					}
				} elseif($answer_check_type==1) // 모두일치하면 정답
				{
					$cnt = 0;
					foreach($q_answer_list as $key => $val)
					{
						if(in_array($val, $answer_list))
						{
							$cnt++;
						}
					}
					if($cnt==$q_answer_count) $check = 'O';
				} else { // 하나만 일치해도 정답.
					foreach($answer_list as $key => $val)
					{
						if(in_array($val, $q_answer_list))
						{
							$check = 'O';
							break;
						}
					}
				}
			} else { // 복수 정답이 아닐때..
				if($answer==$q_answer)
				{
					$check = 'O';
				}
			}

			// check에 따라 처리
			if($check=='O') {
				$_score = $default_score;
				$score += $_score;
				$correct_count++;
			} elseif($check=="P") {
				$_score = ceil(($default_score/$q_answer_count)*$cnt);
				$score += $_score;
				$correct_count++;
			} else {
				$wrong_count++;
			}
			$qitem->add('score',$_score);
			$qitem->add('my_answer',$answer);
			$qitem->add('my_answer_result',$check);
			$answers[$no] = $qitem;
		}
		$answers = serialize($answers);

		// 커트라인 이상일경우 P/..N
		$score = ceil($score);
		$status = ($score >= $examitem->get('cutline'))? "P" : "N";

		//score
		$new_args = new StdClass();
		$new_args->module_srl = $args->module_srl;
		$new_args->document_srl = $args->document_srl;
		$new_args->member_srl = $logged_info->member_srl;
		$new_args->answer = $answers;
		$new_args->correct_count = $correct_count;
		$new_args->wrong_count = $wrong_count;
		$new_args->exam_time = $exam_time;
		$new_args->score = $score;
		$new_args->status = $status;

		// 시험 응시 기록 남기고 세션 삭제
		$output = $this->insertResult($new_args);
		if(!$output->toBool()) return $output;
		unset($_SESSION['exam_joinlog'][$examitem->document_srl]);

		// 만약 합격했을경우 포인트 또는 그룹변경 처리!(v0.5 추가)
		if($status=='P' && !$examitem->isGranted())
		{
			// 합격 포인트 지급!
			if($this->module_info->exam_pass_point_option && $examitem->get('pass_point'))
			{
				$oPointModel = getModel('point');
				$oPointController = getController('point');
				// 차감설정 Y이면 작성자 감소
				if($this->module_info->exam_pass_point_minus=='Y')
				{
					// 작성자의 포인트가 작으면 에러..
					$writer_point = $oPointModel->getPoint($examitem->get('member_srl'));
					if($writer_point >= $examitem->get('pass_point')) {
						$oPointController->setPoint($examitem->get('member_srl'), $examitem->get('pass_point'), 'minus');
						$oPointController->setPoint($logged_info->member_srl, $examitem->get('pass_point'), 'add');
					}
				} else {
					$oPointController->setPoint($logged_info->member_srl, $examitem->get('pass_point'), 'add');
				}
			}
			// 합격 그룹 변경
			if($this->module_info->exam_pass_group_option && $examitem->get('passGroupList'))
			{
				$new_group_list = $examitem->get('passGroupList');
				if(count($new_group_list) > 0)
				{
					$new_args = new StdClass();
					$new_args->member_srl = $logged_info->member_srl;
					$new_args->site_srl = 0;
					// 기존 그룹 모두 삭제
					$output = executeQuery('member.deleteMemberGroupMember', $new_args);
					if(!$output->toBool())
					{
						$oDB->rollback();
						return $output;
					}
					// 그룹 새로 추가
					$oMemberController = getController('member');
					foreach($new_group_list as $group_srl => $group_name)
					{
						$output = $oMemberController->addMemberToGroup($new_args->member_srl,$group_srl);
						if(!$output->toBool())
						{
							$oDB->rollback();
							return $output;
						}
					}
				}
			}
		}

		// 성공 시 시험 목록으로 이동
		$this->setMessage('success_saved');
		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid,'document_srl',$args->document_srl);
		$this->setRedirectUrl($returnUrl);
	}
	function insertExam($obj,$manual_inserted=FALSE)
	{
		if(!$manual_inserted && !checkCSRF())
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}

		if(!$obj->title) return $this->makeObject(-1, 'msg_not_exam_title');
		if($obj->cutline > 100) return $this->makeObject(-1, 'msg_invalid_exam_cutline');

		// 시험기간 설정시 시작일 또는 종료일을 입력했는지 체크
		if($obj->is_date=="Y") {
			if(!$obj->start_date && !$obj->end_date) return $this->makeObject(-1, 'msg_not_exam_date');

			// 종료일이 시작일보다 빠른지 체크
			if(($obj->start_date && $obj->end_date) && ($obj->start_date > $obj->end_date)) return $this->makeObject(-1, 'msg_invalid_start_time');
		}
		// 제한시간 설정시 시간 입력했는지 체크
		if($obj->is_time=="Y") {
			if(!$obj->exam_time) return $this->makeObject(-1, 'msg_not_exam_time');
		}
		unset($obj->regdate);

		// HTML 태그 제거
		$obj->content = nl2br(htmlspecialchars($obj->content, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));

		// begin transaction
		$oDB = &DB::getInstance();
		$oDB->begin();

		// Call a trigger (before)
		$output = ModuleHandler::triggerCall('exam.createExam', 'before', $obj);
		if(!$output->toBool()) return $output;

		$obj->document_srl = getNextSequence();
		$obj->list_order = $obj->document_srl * -1;
		$oDocumentModel = getModel('document');

		// category 값이 있을때 체크
		if($obj->category_srl)
		{
			$category_list = $oDocumentModel->getCategoryList($obj->module_srl);
			if(count($category_list) > 0 && !$category_list[$obj->category_srl]->grant)
			{
				return $this->makeObject(-1, 'msg_not_permitted');
			}
			if(count($category_list) > 0 && !$category_list[$obj->category_srl]) $obj->category_srl = 0;
		}

		// 관리자가 아닐경우 Hack Tag 제거
		if($logged_info->is_admin != 'Y') $obj->content = removeHackTag($obj->content);
		$obj->join_count = $obj->requestion_count = 0;
		$obj->status = 'Y';

		// Insert member's information only if the member is logged-in and not manually registered.
		$logged_info = Context::get('logged_info');
		if(Context::get('is_logged'))
		{
			$obj->member_srl = $logged_info->member_srl;
			$obj->user_name = htmlspecialchars_decode($logged_info->user_name);
			$obj->nick_name = htmlspecialchars_decode($logged_info->nick_name);
		} else {
			$obj->member_srl = 0;
			$obj->user_name = $obj->nick_name = '';
		}

		// Insert data into the DB
		$output = executeQuery('exam.createExam', $obj);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		// Update the category if the category_srl exists.
		if($obj->category_srl) $this->updateCategoryCount($obj->module_srl, $obj->category_srl);
		// Call a trigger (after)
		if($output->toBool())
		{
			$trigger_output = ModuleHandler::triggerCall('exam.createExam', 'after', $obj);
			if(!$trigger_output->toBool())
			{
				$oDB->rollback();
				return $trigger_output;
			}
		}

		// commit
		$oDB->commit();

		// return
		$this->addGrant($obj->document_srl);
		$output->add('document_srl',$obj->document_srl);
		$output->add('category_srl',$obj->category_srl);
		return $output;
	}
	function updateExam($source_obj,$obj,$manual_updated=FALSE)
	{
		if(!$manual_updated && !checkCSRF())
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}

		if(!$source_obj->document_srl || !$obj->document_srl) return $this->makeObject(-1,'msg_invalied_request');
		if(!$obj->title) return $this->makeObject(-1, 'msg_not_exam_title');
		// 시험기간 설정시 시작일 또는 종료일을 입력했는지 체크
		if($obj->is_date=="Y") {
			if(!$obj->start_date && !$obj->end_date) return $this->makeObject(-1, 'msg_not_exam_date');

			// 종료일이 시작일보다 빠른지 체크
			if(($obj->start_date && $obj->end_date) && ($obj->start_date > $obj->end_date)) return $this->makeObject(-1, 'msg_invalid_start_time');
		}
		// 제한시간 설정시 시간 입력했는지 체크
		if($obj->is_time=="Y") {
			if(!$obj->exam_time) return $this->makeObject(-1, 'msg_not_exam_time');
		}
		if($obj->status) $obj->status = $this->getConfigStatus($obj->status,true);

		// Call a trigger (before)
		$output = ModuleHandler::triggerCall('exam.updateExam', 'before', $obj);
		if(!$output->toBool()) return $output;

		// begin transaction
		$oDB = &DB::getInstance();
		$oDB->begin();

		$oModuleModel = getModel('module');
		if(!$obj->module_srl) $obj->module_srl = $source_obj->get('module_srl');

		// HTML 태그 제거
		$obj->content = nl2br(htmlspecialchars($obj->content, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));

		// 관리자가 아닐경우 Hack Tag 제거
		if($logged_info->is_admin != 'Y') $obj->content = removeHackTag($obj->content);

		// 카테고리를 변경했을 때 처리
		$oDocumentModel = getModel('document');
		if($source_obj->get('category_srl')!=$obj->category_srl)
		{
			$category_list = $oDocumentModel->getCategoryList($obj->module_srl);
			if(!$category_list[$obj->category_srl]) $obj->category_srl = 0;
		}

		// Insert data into the DB
		$output = executeQuery('exam.updateExam', $obj);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		// 카테고리의 속한 개수 업데이트
		if($source_obj->get('category_srl') != $obj->category_srl)
		{
			if($source_obj->get('category_srl') != $obj->category_srl) $this->updateCategoryCount($obj->module_srl, $source_obj->get('category_srl'));
			if($obj->category_srl) $this->updateCategoryCount($obj->module_srl, $obj->category_srl);
		}
		// Call a trigger (after)
		if($output->toBool())
		{
			$trigger_output = ModuleHandler::triggerCall('exam.updateExam', 'after', $obj);
			if(!$trigger_output->toBool())
			{
				$oDB->rollback();
				return $trigger_output;
			}
		}

		// commit
		$oDB->commit();
		// Remove the thumbnail file
		FileHandler::removeDir(sprintf('files/thumbnails/%s',getNumberingPath($obj->document_srl, 3)));

		$output->add('document_srl',$obj->document_srl);
		//remove from cache
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			//remove document item from cache
			$cache_key = 'exam_item:'. getNumberingPath($obj->document_srl) . $obj->document_srl;
			$oCacheHandler->delete($cache_key);
		}

		return $output;
	}
	function deleteExam($document_srl,$module_srl)
	{
		$args = new StdClass();
		$args->document_srl = $document_srl;
		$args->module_srl = $module_srl;
		$output = executeQuery('exam.deleteExamByModuleSrl', $args);
		if(!$output->toBool()) return $output;
		$output = executeQuery('exam.deleteExamQuestionByModuleSrl', $args);
		if(!$output->toBool()) return $output;
		$output = executeQuery('exam.deleteExamResultByModuleSrl', $args);
		return $output;
	}
	/**
	 * 해당 시험에 대한 문제 추가
	 * @param object $object
	 * @return object
	 */
	function insertQuestion($obj)
	{
		unset($obj->regdate);
		if(!$obj->module_srl || !$obj->document_srl) return $this->makeObject(-1, 'msg_not_founded');
		if(!$obj->question_srl) $obj->question_srl = getNextSequence();
		$obj->list_order = $obj->question_srl * -1;
		$output = executeQuery('exam.insertQuestion', $obj);
		if($output->toBool())
		{
			// 해당시험에 question_count+1
			$this->updateQuestionCount($obj->document_srl);
		}
		return $output;
	}
	/**
	 * 시험 문제 수정
	 * @param object $obj
	 * @return object
	 */
	function updateQuestion($obj)
	{
		if(!$obj->question_srl) return $this->makeObject(-1, 'msg_not_founded');
		$output = executeQuery('exam.updateQuestion', $obj);
		return $output;
	}
	/**
	 * 시험 문제 삭제
	 * @param object $object
	 * @return object
	 */
	function deleteQuestion($obj)
	{
		if(!$obj->question_srl || !$obj->document_srl) return $this->makeObject(-1, 'msg_not_founded');
		$output = executeQuery('exam.deleteQuestion', $obj);
		if($output->toBool())
		{
			// 해당시험에 question_count+1
			$this->updateQuestionCount($obj->document_srl);
		}
		return $output;
	}
	/**
	 * 시험응시 후 결과 기록
	 * @param object $obj
	 * @return object
	 */
	function insertResult($obj)
	{
		$obj->log_srl = getNextSequence();
		$output = executeQuery('exam.insertResult', $obj);
		if($output->toBool())
		{
			// 해당시험에 join_count+1
			$this->updateJoinCount($obj->document_srl);
		}
		return $output;
	}
	/**
	 * 시험결과 수정
	 * @param object $obj
	 * @return object
	 */
	function updateResult($obj)
	{
		if(!$obj->log_srl) return $this->makeObject(-1, 'msg_not_founded');
		$output = executeQuery('exam.updateResult', $obj);
		return $output;
	}
	/**
	 * 시험 응시 현황 기록 삭제
	 * @param object $object (log_srl, document_srl)
	 * @return object
	 */
	function deleteResult($obj)
	{
		if(!$obj->log_srl || !$obj->document_srl) return $this->makeObject(-1, 'msg_not_founded');
		$output = executeQuery('exam.deleteResult', $obj);
		if($output->toBool())
		{
			// 해당시험에 응시자수 -1
			$this->updateJoinCount($obj->document_srl);
		}

		return $output;
	}
	/**
	 * 해당 시험의 문제개수 업데이트
	 * @param int $document_srl
	 * @param int $question_count
	 * @return object
	 */
	function updateQuestionCount($document_srl, $question_count = 0)
	{
		// Create a document model object
		$oExamModel = getModel('exam');
		if(!$question_count) $question_count = $oExamModel->getQuestionCount($document_srl);

		$args = new stdClass;
		$args->document_srl = $document_srl;
		$args->question_count = $question_count;
		$output = executeQuery('exam.updateExamCount', $args);

		return $output;
	}
	/**
	 * 해당 시험의 응시자수 업데이트
	 * @param int $document_srl
	 * @param int $join_count
	 * @return object
	 */
	function updateJoinCount($document_srl, $join_count = 0)
	{
		$oExamModel = getModel('exam');
		if(!$question_count) $join_count = $oExamModel->getJoinCount($document_srl);

		$args = new stdClass;
		$args->document_srl = $document_srl;
		$args->join_count = $join_count;
		$output = executeQuery('exam.updateExamCount', $args);

		return $output;
	}
	/**
	 * 해당 카테고리의 개수 업데이트
	 * @param int $module_srl
	 * @param int $category_srl
	 * @param int $document_count
	 * @return object
	 */
	function updateCategoryCount($module_srl, $category_srl, $document_count = 0)
	{
		// Create a document model object
		$oExamModel = getModel('exam');
		if(!$document_count) $document_count = $oExamModel->getCategoryExamCount($module_srl,$category_srl);

		$oDocumentController = getController('document');

		$args = new stdClass;
		$args->category_srl = $category_srl;
		$args->document_count = $document_count;
		$output = executeQuery('exam.updateCategoryCount', $args);
		if($output->toBool()) $oDocumentController->makeCategoryFile($module_srl);

		return $output;
	}
	/**
	 * 해당 시험에 대한 권한 추가
	 * @param int $document_srl
	 * @return void
	 */
	function addGrant($document_srl)
	{
		$_SESSION['own_exam'][$document_srl] = true;
	}
	/**
	 * 회원탈퇴시 회원이 응시했던 시험기록 삭제..!
	 * @param object $obj
	 * @return object
	 */
	function triggerDeleteMember($obj)
	{
		$output = executeQuery('exam.deleteResultByMemberSrl.xml', $obj);
		return $output;
	}
}

/* End of file: exam.controller.php */