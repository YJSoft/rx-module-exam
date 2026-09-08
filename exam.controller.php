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
	 * 사용자가 입력한 날짜를 라이믹스 내부 시간대로 변환한다.
	 *
	 * @param string $date YYYYMMDDHHII 또는 YYYYMMDDHHIISS
	 * @return string
	 */
	private function convertUserDateToInternal($date)
	{
		$date = preg_replace('/[^0-9]/', '', (string)$date);
		if(!$date)
		{
			return '';
		}
		if(strlen($date) === 12)
		{
			$date .= '00';
		}
		if(strlen($date) !== 14)
		{
			return '';
		}

		$timezone = new \DateTimeZone(\Rhymix\Framework\DateTime::getTimezoneForCurrentUser());
		$datetime = \DateTime::createFromFormat('!YmdHis', $date, $timezone);
		$errors = \DateTime::getLastErrors();
		if(!$datetime || ($errors && ($errors['warning_count'] || $errors['error_count'])))
		{
			return '';
		}

		return getInternalDateTime($datetime->getTimestamp(), 'YmdHis');
	}

	/**
	 * 공개된 시험지의 문제 변경이 잠겨 있는지 확인한다.
	 *
	 * @param examItem $examitem
	 * @return bool
	 */
	private function isPublishedQuestionEditLocked($examitem)
	{
		$lock_published_exam_questions = $this->module_info->lock_published_exam_questions ?? 'Y';
		return $examitem->getStatus() === 'Y' && $lock_published_exam_questions === 'Y';
	}

	/**
	 * 문제 변경 후 기존 응시 결과를 무효화한다. 포인트 및 그룹은 변경하지 않는다.
	 *
	 * @param examItem $examitem
	 * @return BaseObject|Object
	 */
	private function invalidateResultsAfterQuestionChange($examitem)
	{
		$args = new stdClass();
		$args->module_srl = (int)$examitem->get('module_srl');
		$args->document_srl = (int)$examitem->document_srl;

		$oDB = DB::getInstance();
		$oDB->begin();
		$output = executeQuery('exam.deleteExamResultByModuleSrl', $args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$count_args = new stdClass();
		$count_args->document_srl = (int)$examitem->document_srl;
		$count_args->join_count = 0;
		$output = executeQuery('exam.updateExamCount', $count_args);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$oDB->commit();
		return $this->makeObject();
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

		// 시험별 응시 가능 그룹 설정을 검증한다.
		$join_group_list = array();
		if(($this->module_info->use_exam_join_group ?? 'N') === 'Y')
		{
			$member_group_list = getModel('member')->getGroups();
			foreach((array)($args->exam_join_group_list ?? array()) as $group_srl)
			{
				$group_srl = (int)$group_srl;
				if($group_srl && isset($member_group_list[$group_srl])) $join_group_list[$group_srl] = $group_srl;
			}
		}
		$args->join_group_list = implode(',', $join_group_list);
		unset($args->exam_join_group_list);

		// 합격시 그룹변경 사용시 입력값 체크..
		if($this->module_info->exam_pass_group_option && !empty($args->exam_pass_group_list))
		{
			$group_list = array();
			$selected_group_list = is_array($args->exam_pass_group_list)
				? $args->exam_pass_group_list
				: (array)$args->exam_pass_group_list;
			if($this->module_info->exam_pass_group_option>1)
			{
				$default_group_list = array_filter(explode(",", (string)$this->module_info->exam_pass_group_list), 'strlen');
				foreach($selected_group_list as $group_srl)
				{
					if(!in_array($group_srl, $default_group_list)) continue;
					$group_list[] = $group_srl;
				}
			} else {
				$default_group_list = (array)($logged_info->group_list ?? array());
				foreach($selected_group_list as $group_srl)
				{
					if(!array_key_exists($group_srl, $default_group_list)) continue;
					$group_list[] = $group_srl;
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
		$args->allow_retake = ($args->allow_retake==="Y")? "Y" : "N";
		$args->random_question_order = (($args->random_question_order ?? '') === 'Y') ? 'Y' : 'N';
		$requested_status = isset($args->status) ? ($args->status === 'Y' ? 'Y' : 'N') : null;
		$start_date_input = trim((string)($args->start_date ?? ''));
		$end_date_input = trim((string)($args->end_date ?? ''));
		$args->start_date = $args->is_date === 'Y' ? $this->convertUserDateToInternal($start_date_input) : '';
		$args->end_date = $args->is_date === 'Y' ? $this->convertUserDateToInternal($end_date_input) : '';
		if($args->is_date === 'Y' && (($start_date_input && !$args->start_date) || ($end_date_input && !$args->end_date)))
		{
			return $this->makeObject(-1, 'msg_invalid_start_time');
		}

		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($args->document_srl);
		$is_update = FALSE;
		if($examitem->isExists() && $examitem->document_srl==$args->document_srl)
		{
			$is_update = TRUE;
		}
		if($is_update && !$examitem->isGranted())
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}

		// 시험지 신규 생성 시 포인트 모듈의 글 작성 포인트 설정을 적용한다.
		$create_point = 0;
		if(!$is_update)
		{
			if(!Context::get('is_logged') || empty($logged_info->member_srl))
			{
				return $this->makeObject(-1, 'msg_not_permitted');
			}

			$create_point = (int)PointModel::getModulePointConfig($this->module_srl, 'insert_document');
			if($create_point < 0 && PointModel::getPoint($logged_info->member_srl) + $create_point < 0)
			{
				return $this->makeObject(-1, 'msg_not_enough_point');
			}
		}
		$args->status = $is_update ? ($requested_status ?? $examitem->getStatus()) : ($requested_status ?? 'N');
		// 시험지 설정만 수정할 때 기존 수동 배점 합계가 초기화되지 않도록 보존한다.
		$args->total_point = $is_update ? (int)$examitem->getAllQuestionPoint() : 0;
		unset($args->exam_total_point);

		// update
		if($is_update)
		{
			$output = $this->updateExam($examitem, $args);
		} else { // insert
			$output = $this->insertExam($args, FALSE, $create_point);
		}

		// if there is an error
		if(!$output->toBool())
		{
			return $output;
		}

		// 성공 시 해당 응시 기록의 결과 요약 화면으로 이동
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

		// 시험지 정보를 구해와서, 문제 출제 권한이 있는지 체크
		$document_srl = Context::get('document_srl');
		$question_srl = Context::get('question_srl');

		$examitem = $oExamModel->getExam($document_srl);
		if(!$examitem->isExists()) return $this->makeObject(-1, 'msg_not_founded');
		if(!$examitem->isGranted()) return $this->makeObject(-1, 'msg_not_permitted');
		if($this->isPublishedQuestionEditLocked($examitem)) return $this->makeObject(-1, 'msg_published_exam_question_locked');

		// question_srl이 있으면 문제정보도 체크
		$questionitem = $oExamModel->getQuestion($question_srl);
		if($questionitem->isExists() && (int)$questionitem->get('document_srl') !== (int)$examitem->document_srl)
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}
		// srl값이 있고 해당 문제가 존재하면 수정처리, 아니면 신규처리
		if($question_srl && $questionitem->isExists())
		{
			$is_mode = 'update';
		} else {
			$is_mode = 'insert';
		}

		//  var setting
		$params = Context::getRequestVars();
		$question_type = (int)($params->q_type ?? 0);
		if(!in_array($question_type, array(0, 1, 2), true)) $question_type = 0;
		$passage_questions = array();
		$passage_selected_srls = array();

		// 정답이 하나도 입력안되있을경우..
		if($question_type === 2)
		{
			if($examitem->getQuestionCount() < 1)
			{
				return $this->makeObject(-1, 'msg_passage_requires_questions');
			}
			$passage_start = (int)($params->passage_start ?? 0);
			$passage_end = (int)($params->passage_end ?? 0);
			if(!($params->q_description_content ?? '') || $passage_start < 1 || $passage_end < $passage_start)
			{
				return $this->makeObject(-1, 'msg_invalid_passage_range');
			}

			foreach(($examitem->getQuestions() ?: array()) as $qitem)
			{
				if((int)$qitem->question_srl === (int)$question_srl) continue;
				$passage_questions[] = $qitem;
			}
			if($passage_end > count($passage_questions))
			{
				return $this->makeObject(-1, 'msg_invalid_passage_range');
			}

			for($i = $passage_start; $i <= $passage_end; $i++)
			{
				$qitem = $passage_questions[$i - 1];
				$linked_passage_srl = (int)$qitem->get('passage_srl');
				if($linked_passage_srl && $linked_passage_srl !== (int)$question_srl)
				{
					return $this->makeObject(-1, 'msg_passage_range_overlap');
				}
				$passage_selected_srls[(int)$qitem->question_srl] = true;
			}
			$params->q_answer = '';
		}
		elseif($question_type === 1)
		{
			if(($params->q_answer6 ?? '') == "") return $this->makeObject(-1, 'msg_not_answer');
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
				if($params->{'q_answer'.$val} == "") continue;
				$new_ans_list[] = $val;
			}
			$params->q_answer = implode(",", $new_ans_list);
		}
		$args = new StdClass();
		$args->question_level = $question_type === 2 ? 0 : (int)$params->q_level;
		$args->question_type = $question_type;
		$args->passage_srl = $question_type === 2 ? 0 : (int)$questionitem->get('passage_srl');
		$args->title = escape($params->q_title ?? '', false);
		$args->content = $question_type === 2 ? '' : ($params->q_content ?? '');
		$args->use_description = $question_type === 2 ? 'Y' : ((($params->use_description ?? '') == 'Y') ? 'Y' : 'N');
		$args->description_title = $question_type === 2 ? '' : escape($params->q_description_title ?? '', false);
		$args->description = removeHackTag($params->q_description_content ?? '');
		$args->answer = $params->q_answer;
		$args->answer_check_type = $question_type === 2 ? 0 : (int)$params->answer_check_type;
		$args->point = $question_type === 2 ? 0 : (int)$params->q_point;
		for($i=1;$i<=5;$i++)
		{
			$args->{'answer'.$i} = $question_type === 2 ? '' : ($params->{'q_answer'.$i} ?? '');
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

		if(!$output->toBool()) return $this->makeObject(-1,"Failed to insert/update Question");
		if($question_type === 2)
		{
			foreach($passage_questions as $qitem)
			{
				$current_passage_srl = (int)$qitem->get('passage_srl');
				$new_passage_srl = isset($passage_selected_srls[(int)$qitem->question_srl])
					? (int)$args->question_srl
					: ($current_passage_srl === (int)$args->question_srl ? 0 : $current_passage_srl);
				if($new_passage_srl === $current_passage_srl) continue;

				$passage_output = $this->updateQuestionPassage($document_srl, $qitem->question_srl, $new_passage_srl);
				if(!$passage_output->toBool()) return $passage_output;
			}
		}
		elseif($questionitem->isExists() && $questionitem->isPassage())
		{
			$passage_output = $this->clearQuestionPassage($document_srl, $question_srl);
			if(!$passage_output->toBool()) return $passage_output;
		}
		$count_output = $this->updateQuestionCount($document_srl);
		if(!$count_output->toBool()) return $count_output;
		if((int)$questionitem->get('point') !== (int)$args->point)
		{
			$total_point = 0;
			$question_list = $oExamModel->getQuestionList($document_srl);
			$questions = $question_list->data ?? array();
			if(count($questions))
			{
				foreach($questions as $key => $val)
				{
					$total_point += $val->point;
				}
			}
			else
			{
				$total_point += $args->point;
			}
	
			$output = $this->updateTotalPoint($document_srl, $total_point);
			if(!$output->toBool()) return $this->makeObject(-1,"Failed to update total_point");
	
			//remove from cache
			$oCacheHandler = CacheHandler::getInstance('object');
			if($oCacheHandler->isSupport())
			{
				//remove document item from cache
				$cache_key = 'exam_item:'. getNumberingPath($document_srl) . $document_srl;
				$oCacheHandler->delete($cache_key);
			}
		}
		$output = $this->invalidateResultsAfterQuestionChange($examitem);
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

		// 시험지 정볼르 구해와서, 문제 출제 권한이 있는지 체크
		$document_srl = Context::get('document_srl');
		$question_srl = Context::get('question_srl');

		// 권한 체크
		$examitem = $oExamModel->getExam($document_srl);
		if(!$examitem->isExists()) return $this->makeObject(-1, 'msg_not_founded');
		if(!$examitem->isGranted()) return $this->makeObject(-1, 'msg_not_permitted');
		if($this->isPublishedQuestionEditLocked($examitem)) return $this->makeObject(-1, 'msg_published_exam_question_locked');

		// question_srl이 있으면 문제정보도 체크
		$questionitem = $oExamModel->getQuestion($question_srl);
		if(!$questionitem->isExists()) return $this->makeObject(-1, 'msg_not_founded');
		if($questionitem->get('document_srl')!=$examitem->document_srl) return $this->makeObject(-1, 'msg_invalid_request');

		// 질문 삭제
		$args = new StdClass();
		$args->question_srl = $question_srl;
		$args->document_srl = $document_srl;
		if($questionitem->isPassage())
		{
			$output = $this->clearQuestionPassage($document_srl, $question_srl);
			if(!$output->toBool()) return $output;
		}
		$output = $this->deleteQuestion($args);
		if(!$output->toBool()) return $this->makeObject(-1, "msg_invalid_request");
		
		// 전체 배점을 수정
		$total_point = $examitem->getAllQuestionPoint() - $questionitem->getPoint();
		$output = $this->updateTotalPoint($document_srl, $total_point);
		if(!$output->toBool()) return $this->makeObject(-1, "msg_invalid_request");

		// 캐시 삭제
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			//remove document item from cache
			$cache_key = 'exam_item:'. getNumberingPath($document_srl) . $document_srl;
			$oCacheHandler->delete($cache_key);
		}
		$output = $this->invalidateResultsAfterQuestionChange($examitem);
		if(!$output->toBool()) return $output;

		$this->setMessage('success_deleted');
	}
	/**
	 * @brief 시험지에 문제 순서 변경 처리
	 */
	public function procExamQuestionSwap()
	{
		// 권한 체크
		if ( $this->module_info->module !== 'exam' ) return $this->makeObject(-1, 'msg_invalid_request');
		if ( !$this->grant->create ) return $this->makeObject(-1, 'msg_not_permitted');
		$oExamModel = getModel('exam');

		// 선택된 문제 번호로 php 변수에 데이터 담아두기
		$selected_srl = Context::get('selected_srl');
		$selected_item = $oExamModel->getQuestion($selected_srl);

		// 표적화된 문제번호로 php 변수에 데이터 담아두기
		$targeted_srl = Context::get('targeted_srl');
		$targeted_item = $oExamModel->getQuestion($targeted_srl);
		if(!$selected_item->isExists() || !$targeted_item->isExists() || $selected_item->isPassage() || $targeted_item->isPassage())
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}
		if((int)$selected_item->get('document_srl') !== (int)$targeted_item->get('document_srl'))
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}
		$examitem = $oExamModel->getExam($selected_item->get('document_srl'));
		if(!$examitem->isExists() || !$examitem->isGranted())
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}
		if($this->isPublishedQuestionEditLocked($examitem))
		{
			return $this->makeObject(-1, 'msg_published_exam_question_locked');
		}

		// 선택 문제에 표적 문제의 데이터를 이전
		$args = new StdClass;
		$args->question_srl = $selected_srl;
		$args->question_level = $targeted_item->get('question_level');
		$args->question_type = $targeted_item->get('question_type');
		$args->passage_srl = $targeted_item->get('passage_srl');
		$args->title = $targeted_item->get('title');
		$args->content = $targeted_item->get('content');
		$args->answer = $targeted_item->get('answer');
		for ( $i = 1; $i <= 5; $i++ )
		{
			$args->{'answer'.$i} = $targeted_item->get('answer'.$i);
		}
		$args->answer_check_type = $targeted_item->get('answer_check_type');
		$args->use_description = $targeted_item->get('use_description');
		$args->description_title = $targeted_item->get('description_title');
		$args->description = $targeted_item->get('description');
		$args->regdate = $targeted_item->get('regdate');
		$args->ipaddress = $targeted_item->get('ipaddress');
		$args->status = $targeted_item->get('status');
		$args->point = $targeted_item->get('point');
		$output = $this->updateQuestion($args);
		if(!$output->toBool()) return $output;

		// 표적 문제에 선택 문제의 데이터를 이전
		$args = new StdClass;
		$args->question_srl = $targeted_srl;
		$args->question_level = $selected_item->get('question_level');
		$args->question_type = $selected_item->get('question_type');
		$args->passage_srl = $selected_item->get('passage_srl');
		$args->title = $selected_item->get('title');
		$args->content = $selected_item->get('content');
		$args->answer = $selected_item->get('answer');
		for ( $i = 1; $i <= 5; $i++ )
		{
			$args->{'answer'.$i} = $selected_item->get('answer'.$i);
		}
		$args->answer_check_type = $selected_item->get('answer_check_type');
		$args->use_description = $selected_item->get('use_description');
		$args->description_title = $selected_item->get('description_title');
		$args->description = $selected_item->get('description');
		$args->regdate = $selected_item->get('regdate');
		$args->ipaddress = $selected_item->get('ipaddress');
		$args->status = $selected_item->get('status');
		$args->point = $selected_item->get('point');
		$output = $this->updateQuestion($args);
		if(!$output->toBool()) return $output;

		return $this->invalidateResultsAfterQuestionChange($examitem);
	}
	/**
	 * 객관식 복수답안을 채점한다.
	 * 제출한 답안에 오답이 하나라도 포함되면 정답 유형과 관계없이 오답 처리한다.
	 *
	 * @param string $answer 응시자가 제출한 답안
	 * @param array $q_answer_list 문제의 정답 목록
	 * @param int $answer_check_type 0: 일부 일치, 1: 모두 일치, 2: 모두 일치 및 부분점수
	 * @return array
	 */
	private function checkMultipleChoiceAnswer($answer, $q_answer_list, $answer_check_type)
	{
		$normalize_answer_list = static function($answer_list)
		{
			if(!is_array($answer_list))
			{
				$answer_list = explode(',', (string)$answer_list);
			}

			$answer_list = array_map('trim', $answer_list);
			$answer_list = array_filter($answer_list, static function($value)
			{
				return $value !== '';
			});

			return array_values(array_unique($answer_list, SORT_STRING));
		};

		$answer_list = $normalize_answer_list($answer);
		$q_answer_list = $normalize_answer_list($q_answer_list);
		$matched_count = count(array_intersect($answer_list, $q_answer_list));
		$q_answer_count = count($q_answer_list);
		$has_wrong_answer = count(array_diff($answer_list, $q_answer_list)) > 0;

		$check = 'X';
		if(!$has_wrong_answer && $matched_count > 0)
		{
			$all_match = count($answer_list) === $q_answer_count;
			if((int)$answer_check_type === 0)
			{
				$check = 'O';
			}
			elseif($all_match)
			{
				$check = 'O';
			}
			elseif((int)$answer_check_type === 2)
			{
				$check = 'P';
			}
		}

		return array(
			'check' => $check,
			'matched_count' => $matched_count,
			'answer_count' => $q_answer_count,
		);
	}

	/**
	 * 최초 합격자에게 설정된 포인트와 그룹 혜택을 적용한다.
	 *
	 * @param examItem $examitem
	 * @param int $member_srl
	 * @return BaseObject|Object
	 */
	private function grantPassBenefits($examitem, $member_srl)
	{
		if($this->module_info->exam_pass_point_option && $examitem->get('pass_point'))
		{
			$oPointModel = getModel('point');
			$oPointController = getController('point');
			if($this->module_info->exam_pass_point_minus === 'Y')
			{
				$writer_point = $oPointModel->getPoint($examitem->get('member_srl'));
				if($writer_point >= $examitem->get('pass_point'))
				{
					$oPointController->setPoint($examitem->get('member_srl'), $examitem->get('pass_point'), 'minus');
					$oPointController->setPoint($member_srl, $examitem->get('pass_point'), 'add');
				}
			}
			else
			{
				$oPointController->setPoint($member_srl, $examitem->get('pass_point'), 'add');
			}
		}

		if($this->module_info->exam_pass_group_option && $examitem->get('passGroupList'))
		{
			$new_group_list = $examitem->get('passGroupList');
			if(count($new_group_list) > 0)
			{
				$args = new stdClass();
				$args->member_srl = $member_srl;
				$args->site_srl = 0;
				$output = executeQuery('member.deleteMemberGroupMember', $args);
				if(!$output->toBool())
				{
					return $output;
				}
				$oMemberController = getController('member');
				foreach($new_group_list as $group_srl => $group_name)
				{
					$output = $oMemberController->addMemberToGroup($member_srl, $group_srl);
					if(!$output->toBool())
					{
						return $output;
					}
				}
			}
		}

		return $this->makeObject();
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
		$abandon_attempt = (($args->abandon_attempt ?? '') === 'Y');
		if(!$args->document_srl || !$args->module_srl) return $this->makeObject(-1, 'msg_not_founded');

		// 응시한 시험정보 구해옴
		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($args->document_srl);
		if(!$examitem->isExists()) return $this->makeObject(-1, 'msg_not_founded');
		if((int)$examitem->get('module_srl') !== (int)$this->module_info->module_srl || (int)$args->module_srl !== (int)$this->module_info->module_srl)
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}
		if($examitem->isSecret() && !$examitem->isGranted())
		{
			return $this->makeObject(-1, 'msg_not_founded');
		}
		// 이어서 응시할 수 있는 시험은 이탈 알림 요청으로 제출하지 않는다.
		$abandon_attempt = $abandon_attempt && $examitem->get('allow_retake') !== 'Y';

		$logged_info = Context::get('logged_info');
		if(!$examitem->isJoinGroupAllowed($logged_info))
		{
			return $this->makeObject(-1, 'msg_not_allowed_exam_group');
		}

		// 이미 응시했으면...
		$resultitem = $oExamModel->getExamResultByDocumentSrl($examitem->document_srl,$logged_info->member_srl);
		if($resultitem && $resultitem->log_srl && $examitem->get('allow_retake') !== 'Y') return $this->makeObject(-1, 'msg_exists_result');
		$has_passed_before = $oExamModel->hasPassedExam($examitem->document_srl, $logged_info->member_srl);

		// 시작시 생선한 세션이 존재하는지 체크
		if(empty($_SESSION['exam_joinlog'][$examitem->document_srl])) return $this->makeObject(-1, 'msg_not_session');
		if((int)($_SESSION['exam_joinlog'][$examitem->document_srl]['member_srl'] ?? 0) !== (int)$logged_info->member_srl)
		{
			return $this->makeObject(-1, 'msg_not_session');
		}

		// 혹시나.. 출제된 문제가 없는데 전송된경우 체크
		if(!$examitem->get('question_count')) return $this->makeObject(-1,'no_question_list');

		// 시험기간인지 체크함
		$now = time();
		if($examitem->isDate() && ($examitem->get('start_date') || $examitem->get('end_date')))
		{
			if($examitem->get('start_date') && ztime($examitem->get('start_date')) > $now) return $this->makeObject(-1, 'msg_not_exam_date');
			if($examitem->get('end_date') && $now > ztime($examitem->get('end_date'))) return $this->makeObject(-1, 'msg_not_exam_date');
		}
		// 제한시간 체크
		$start_date = $_SESSION['exam_joinlog'][$examitem->document_srl]['start_date'];
		$start_time = $_SESSION['exam_joinlog'][$examitem->document_srl]['start_time'] ?? ztime($start_date);
		if(!$start_time) return $this->makeObject(-1, 'msg_not_session');
		// 재응시가 허용되지 않은 시험에서 페이지를 이탈한 경우 미응답으로 제출한다.
		$force_empty_answers = $abandon_attempt;
		if($examitem->isTime() && $examitem->get('exam_time'))
		{
			// 끝나는시간 (시작시간+제한시간 초)
			$end_date = $start_time + (int)$examitem->get('exam_time');
			$over_time = $now-$end_date;
			// 만료 후 재접속한 경우 오류로 중단하지 않고 미응답 상태로 자동 채점한다.
			// 정상적인 제출 처리시간은 기존과 같이 10초까지 허용한다.
			if($over_time > 10) $force_empty_answers = true;
		}
		$exam_time = max(0, $now-$start_time);

		// 문제 정답 체크
		$answers = array();
		$correct_count = 0;
		$wrong_count = 0;
		$score = 0;

		$default_score = ceil(1 / $examitem->get('question_count') * 100); //문제당 기본 배점(1/총문제수*100)
		$use_question_point = $examitem->getAllQuestionPoint() > 0;
		$question_order = $_SESSION['exam_joinlog'][$examitem->document_srl]['question_order'] ?? array();
		foreach($examitem->getQuestions($question_order) as $no => $qitem)
		{
			$answer = $force_empty_answers ? '' : ($args->{'answer'.$no} ?? ''); // 응시자가 선택(입력)한 답
			$q_answer = $qitem->get('answer'); // 문제 정답
			$q_answer_count = $qitem->getAnswerCount(); // 정답 개수
			$check = 'X'; // O정답, X오답, P부분정답
			$cnt = 0;
			$_score = 0;
			// 객관식이고 복수정답일때 처리
			if(!$qitem->getQType() && $q_answer_count>1)
			{
				$multiple_answer_result = $this->checkMultipleChoiceAnswer(
					$answer,
					$qitem->getAnswerList(),
					$qitem->get('answer_check_type')
				);
				$check = $multiple_answer_result['check'];
				$cnt = $multiple_answer_result['matched_count'];
				$q_answer_count = $multiple_answer_result['answer_count'];
			} else { // 복수 정답이 아닐때..
				if($answer==$q_answer)
				{
					$check = 'O';
				}
			}
			
			//총 배점이 0점이라면 기존 시험이므로 기존 방식대로 채점한다
			if(!$use_question_point) {
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
			} else {
				if($check=='O') {
					$_score = $qitem->get('point');
					$score += $_score;
					$correct_count++;
				} elseif($check=="P") {
					$_score = ceil(($cnt/$q_answer_count)*$qitem->get('point'));
					$score += $_score;
					$correct_count++;
				} else {
					$wrong_count++;
				}
			}

			$qitem->add('score',$_score);
			$qitem->add('my_answer',$answer);
			$qitem->add('my_answer_result',$check);
			$answers[$no] = $qitem;
		}
		
		$answers = serialize($answers);

		// 커트라인 이상일경우 P/..N
		$score = ceil($score);
		$status = $abandon_attempt ? 'N' : (($score >= $examitem->get('cutline')) ? 'P' : 'N');

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

		// 최초 합격일 경우에만 포인트 또는 그룹 변경 처리
		if($status=='P' && !$has_passed_before && !$examitem->isGranted())
		{
			$output = $this->grantPassBenefits($examitem, $logged_info->member_srl);
			if(!$output->toBool()) return $output;
		}

		// 성공 시 시험 목록으로 이동
		$this->setMessage('success_saved');
		$returnUrl = getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispExamCorrectCheck', 'log_srl', $new_args->log_srl);
		$this->setRedirectUrl($returnUrl);
	}

	/**
	 * 출제자 또는 관리자가 응시 결과를 문항별로 수동 채점한다.
	 */
	public function procExamManualGrade()
	{
		if(!checkCSRF())
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}

		$args = Context::getRequestVars();
		if(!$args->log_srl)
		{
			return $this->makeObject(-1, 'msg_not_founded');
		}

		$oExamModel = getModel('exam');
		$resultitem = $oExamModel->getExamResult((int)$args->log_srl);
		if(!$resultitem || !$resultitem->log_srl)
		{
			return $this->makeObject(-1, 'msg_not_founded');
		}

		$examitem = $oExamModel->getExam($resultitem->document_srl);
		if(!$examitem->isExists() || $examitem->get('module_srl') != $this->module_srl)
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}
		if(!$this->grant->manager && !$examitem->isGranted())
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}
		$has_passed_before = $oExamModel->hasPassedExam($examitem->document_srl, $resultitem->member_srl);

		$question_scores = (array)($args->question_score ?? array());
		$answers = is_array($resultitem->answer) ? $resultitem->answer : array();
		$question_count = count($answers);
		$default_score = $question_count ? ceil(100 / $question_count) : 0;
		$stored_total_point = 0;
		foreach($answers as $qitem)
		{
			if(!is_object($qitem) || !method_exists($qitem, 'get'))
			{
				continue;
			}
			$stored_total_point += max(0, (int)$qitem->get('point'));
		}
		$use_question_point = $stored_total_point > 0;
		$correct_count = 0;
		$wrong_count = 0;
		$total_score = 0;

		foreach($answers as $no => $qitem)
		{
			if(!is_object($qitem) || !method_exists($qitem, 'get') || !method_exists($qitem, 'add'))
			{
				continue;
			}
			$max_score = $use_question_point ? max(0, (int)$qitem->get('point')) : $default_score;
			$current_score = max(0, (int)$qitem->get('score'));
			$question_score = array_key_exists($no, $question_scores) ? (int)$question_scores[$no] : $current_score;
			$question_score = min(max(0, $question_score), $max_score);

			if($question_score <= 0)
			{
				$check = 'X';
				$wrong_count++;
			}
			elseif($question_score >= $max_score)
			{
				$check = 'O';
				$correct_count++;
			}
			else
			{
				$check = 'P';
				$correct_count++;
			}

			$qitem->add('score', $question_score);
			$qitem->add('my_answer_result', $check);
			$qitem->add('manual_graded', 'Y');
			$answers[$no] = $qitem;
			$total_score += $question_score;
		}

		$update_args = new stdClass();
		$update_args->log_srl = $resultitem->log_srl;
		$update_args->answer = serialize($answers);
		$update_args->correct_count = $correct_count;
		$update_args->wrong_count = $wrong_count;
		$update_args->exam_time = (int)$resultitem->exam_time;
		$update_args->score = $total_score;
		$update_args->status = $total_score >= (int)$examitem->get('cutline') ? 'P' : 'N';

		$output = $this->updateResult($update_args);
		if(!$output->toBool())
		{
			return $output;
		}
		if($update_args->status === 'P' && !$has_passed_before && $resultitem->member_srl != $examitem->get('member_srl'))
		{
			$output = $this->grantPassBenefits($examitem, $resultitem->member_srl);
			if(!$output->toBool()) return $output;
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'mid', $this->mid, 'act', 'dispExamCorrectCheck', 'log_srl', $resultitem->log_srl));
	}
	function insertExam($obj,$manual_inserted=FALSE,$create_point=0)
	{
		if(!$manual_inserted && !checkCSRF())
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}

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
		unset($obj->regdate);

		// HTML 태그 제거
		$obj->content = nl2br(escape($obj->content, false));

		// begin transaction
		$oDB = &DB::getInstance();
		$oDB->begin();

		// Call a trigger (before)
		$output = ModuleHandler::triggerCall('exam.createExam', 'before', $obj);
		if(!$output->toBool()) return $output;

		$obj->document_srl = getNextSequence();
		$obj->list_order = $obj->document_srl * -1;
		$oDocumentModel = getModel('document');
		$logged_info = Context::get('logged_info');

		// category 값이 있을때 체크
		if($obj->category_srl)
		{
			$category_list = (array)$oDocumentModel->getCategoryList($obj->module_srl);
			$category = $category_list[$obj->category_srl] ?? null;
			if(!$category)
			{
				$obj->category_srl = 0;
			}
			elseif(empty($category->grant))
			{
				return $this->makeObject(-1, 'msg_not_permitted');
			}
		}

		// 관리자가 아닐경우 Hack Tag 제거
		if(($logged_info->is_admin ?? 'N') != 'Y') $obj->content = removeHackTag($obj->content);
		$obj->join_count = $obj->requestion_count = 0;
		$obj->status = $obj->status === 'Y' ? 'Y' : 'N';

		// Insert member's information only if the member is logged-in and not manually registered.
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

		// 수동 등록이 아닌 신규 시험지에만 생성 포인트를 반영한다.
		$create_point = (int)$create_point;
		if(!$manual_inserted && $create_point !== 0 && $obj->member_srl)
		{
			$point_output = PointController::setPoint($obj->member_srl, $create_point, 'plus', 'insert_exam');
			if(!$point_output->toBool())
			{
				$oDB->rollback();
				return $point_output;
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
		$logged_info = Context::get('logged_info');
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
		$obj->status = $obj->status === 'Y' ? 'Y' : 'N';
		if($source_obj->getStatus() === 'Y' && $obj->status === 'N' && ($this->module_info->allow_exam_reprivate ?? 'N') !== 'Y')
		{
			return $this->makeObject(-1, 'msg_exam_reprivate_not_allowed');
		}

		// Call a trigger (before)
		$output = ModuleHandler::triggerCall('exam.updateExam', 'before', $obj);
		if(!$output->toBool()) return $output;

		// begin transaction
		$oDB = &DB::getInstance();
		$oDB->begin();

		$oModuleModel = getModel('module');
		if(!$obj->module_srl) $obj->module_srl = $source_obj->get('module_srl');

		// HTML 태그 제거
		$obj->content = nl2br(escape($obj->content, false));

		// 관리자가 아닐경우 Hack Tag 제거
		if(($logged_info->is_admin ?? 'N') != 'Y') $obj->content = removeHackTag($obj->content);

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
		if($source_obj->get('category_srl') != $obj->category_srl || $source_obj->getStatus() !== $obj->status)
		{
			$category_srls = array_unique(array_filter(array($source_obj->get('category_srl'), $obj->category_srl)));
			foreach($category_srls as $category_srl)
			{
				$this->updateCategoryCount($obj->module_srl, $category_srl);
			}
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

	function updateQuestionPassage($document_srl, $question_srl, $passage_srl)
	{
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->question_srl = $question_srl;
		$args->passage_srl = $passage_srl;
		return executeQuery('exam.updateQuestionPassage', $args);
	}

	function clearQuestionPassage($document_srl, $passage_srl)
	{
		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->passage_srl = $passage_srl;
		return executeQuery('exam.clearQuestionPassage', $args);
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
		if(!$join_count) $join_count = $oExamModel->getJoinCount($document_srl);

		$args = new stdClass;
		$args->document_srl = $document_srl;
		$args->join_count = $join_count;
		$output = executeQuery('exam.updateExamCount', $args);

		return $output;
	}

	/**
	 * 해당 시험의 총 배점
	 * @param int $document_srl
	 * @param int $total_point
	 * @return object
	 */
	function updateTotalPoint($document_srl, $total_point = 0)
	{
		$oExamModel = getModel('exam');

		$args = new stdClass;
		$args->document_srl = $document_srl;
		$args->total_point = $total_point;
		$output = executeQuery('exam.updateExamTotalPoint', $args);

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
		$output = executeQuery('exam.deleteResultByMemberSrl', $obj);
		return $output;
	}
}

/* End of file: exam.controller.php */
