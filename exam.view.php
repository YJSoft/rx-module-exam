<?php
/**
 * @class  examView
 * @author 러키군 (admin@barch.kr)
 * @brief  exam module view class
 */
class examView extends exam
{
	function init()
	{
		$oSecurity = new Security();
		$oSecurity->encodeHTML('document_srl','mid', 'page', 'category', 'search_target', 'search_keyword', 'sort_index', 'order_type');

		// default vars
		if($this->module_info->list_count)
		{
			$this->list_count = $this->module_info->list_count;
		}
		if($this->module_info->page_count)
		{
			$this->page_count = $this->module_info->page_count;
		}
		if(!$this->module_info->duration_new)
		{
			$this->module_info->duration_new = 24;
		}
		if(!isset($this->module_info->allow_exam_reprivate))
		{
			$this->module_info->allow_exam_reprivate = 'N';
		}
		if(!isset($this->module_info->lock_published_exam_questions))
		{
			$this->module_info->lock_published_exam_questions = 'Y';
		}
		if(!isset($this->module_info->use_exam_join_group))
		{
			$this->module_info->use_exam_join_group = 'N';
		}
		if($this->module_info->exam_pass_group_list) $this->module_info->exam_pass_group_list = explode(",", $this->module_info->exam_pass_group_list);

		// 템플릿 경로 지정
		$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
		if(!is_dir($template_path)||!$this->module_info->skin)
		{
			$this->module_info->skin = $this->skin;
			$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
		}
		$this->setTemplatePath($template_path);

		// exam 모듈에서 사용할 자바스크립트 로드
		Context::addJsFile($this->module_path.'tpl/js/exam.js');
	}
	/**
	 * 시험 페이지 출력 (INDEX)
	 **/
	public function dispExamIndex()
	{
		// 접근 권한 체크
		if (!$this->grant->access)
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}
		// 분류 목록 구해와서 세팅
		$this->dispExamCategoryList();

		// 검색옵션 세팅
		foreach($this->search_option as $opt) $search_option[$opt] = Context::getLang($opt);
		Context::set('search_option', $search_option);

		// list config, columnList setting
		$oExamModel = getModel('exam');
		// point module config setting
		$point_config = $oExamModel->getPointConfig();
		Context::set('point_config', $point_config);

		$this->listConfig = $oExamModel->getListConfig($this->module_info->module_srl);
		if(!$this->listConfig) $this->listConfig = array();
		$this->_makeListColumnList();

		// get the variable value
		$document_srl = Context::get('document_srl');
		if($document_srl)
		{
			return $this->dispExamPage();
		}

		// list
		$this->dispExamList();

		// 검색에 필요한 필터파일 로드
		Context::addJsFilter($this->module_path.'tpl/filter', 'search.xml');

		$oSecurity = new Security();
		$oSecurity->encodeHTML('search_option.');

		$this->setTemplateFile('index');
	}
	/**
	 * @brief 분류 목록을 구해와서 세팅함
	 **/
	public function dispExamCategoryList()
	{
		if($this->module_info->hide_category!='Y')
		{
			$oDocumentModel = getModel('document');
			Context::set('category_list', $oDocumentModel->getCategoryList($this->module_srl));

			$oSecurity = new Security();
			$oSecurity->encodeHTML('category_list.', 'category_list.childs.');
		}
	}
	/**
	 * @brief 시험 목록을 구해와서 세팅함.
	 **/
	public function dispExamList()
	{
		// check the grant
		if(!$this->grant->access)
		{
			Context::set('exam_list', array());
			Context::set('total_count', 0);
			Context::set('total_page', 1);
			Context::set('page', 1);
			Context::set('page_navigation', new PageHandler(0,0,1,10));
			return;
		}
		$oExamModel = getModel('exam');

		// setup module_srl/page number/ list number/ page count
		$args = new stdClass();
		$args->module_srl = $this->module_srl;
		$args->page = Context::get('page');
		$args->list_count = $this->list_count;
		$args->page_count = $this->page_count;

		// get the search target and keyword
		$args->search_target = Context::get('search_target');
		$args->search_keyword = Context::get('search_keyword');

		// if the category is enabled, then get the category
		if($this->module_info->hide_category!='Y')
		{
			$args->category_srl = Context::get('category');
		}

		// setup the sort index and order index
		$args->sort_index = Context::get('sort_index');
		$args->order_type = Context::get('order_type');
		if(!in_array($args->sort_index, $this->order_target))
		{
			$args->sort_index = $this->module_info->order_target?$this->module_info->order_target:'list_order';
		}
		if(!in_array($args->order_type, array('asc','desc')))
		{
			$args->order_type = $this->module_info->order_type?$this->module_info->order_type:'asc';
		}

		// setup the list config variable on context
		Context::set('list_config', $this->listConfig);
		// setup document list variables on context
		$output = $oExamModel->getExamList($args, $this->columnList);

		Context::set('exam_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);
	}
	public function _makeListColumnList()
	{
		$configColumList = array_keys($this->listConfig);
		if($configColumList)
		{
			foreach($configColumList as $key)
			{
				if($key!="exam_srl" && substr($key,0,5)=="exam_") $configColumList[$key] = substr($key,5);
			}
		}
		$tableColumnList = array('document_srl', 'module_srl', 'category_srl',  'member_srl', 'user_name','nick_name',
				'title', 'title_bold', 'title_color', 'content', 'page_type', 'result_type','cutline',
				'is_date', 'is_time', 'join_point', 'join_limit_count', 'question_count','join_count',
				'start_date', 'end_date', 'regdate', 'last_update', 'ipaddress', 'status','pass_point','pass_group_list',
				'bank_objective_count', 'bank_subjective_count');
		$this->columnList = array_intersect($configColumList, $tableColumnList);

		// default column list add
		$defaultColumn = array('document_srl', 'module_srl', 'category_srl','member_srl','user_name','nick_name','last_update','status', 'regdate', 'title_bold', 'title_color','is_time','is_date','start_date','end_date','pass_point','pass_group_list','page_type','bank_objective_count','bank_subjective_count');
		$this->columnList = array_unique(array_merge($this->columnList, $defaultColumn));

		// add table name
		foreach($this->columnList as $no => $value)
		{
			$this->columnList[$no] = 'exam.' . $value;
		}
	}
	/**
	 * 시험지 생성페이지 출력
	 **/
	public function dispExamCreate()
	{
		// 권한 체크
		if (!$this->grant->create)
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}
		// examitem 얻기
		$documentSrl = Context::get('document_srl');
		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($documentSrl);
		$logged_info = Context::get('logged_info');

		// point module config setting
		$point_config = $oExamModel->getPointConfig();
		Context::set('point_config', $point_config);
		$exam_create_point = (int)PointModel::getModulePointConfig($this->module_srl, 'insert_document');
		$current_member_point = Context::get('is_logged') && !empty($logged_info->member_srl)
			? PointModel::getPoint($logged_info->member_srl)
			: 0;
		Context::set('exam_create_point', $exam_create_point);
		Context::set('current_member_point', $current_member_point);

		// 존재하는지 확인
		if ($documentSrl)
		{
			if(!$examitem->isExists()) return $this->stop('msg_not_founded');
			if(!$examitem->isGranted()) return $this->stop('msg_not_permitted');
		}

		// 모듈 관리자이면 권한 세팅
		if ($this->grant->manager)
		{
			$examitem->setGrant();
		}
		Context::set('can_make_exam_private',
			!$examitem->isExists() || $examitem->getStatus() === 'N' || $this->module_info->allow_exam_reprivate === 'Y'
		);
		$join_group_options = array();
		if($this->module_info->use_exam_join_group === 'Y')
		{
			$member_group_list = getModel('member')->getGroups();
			foreach($member_group_list as $group_srl => $group_info)
			{
				$join_group_options[$group_srl] = $group_info->title;
			}
		}
		Context::set('join_group_options', $join_group_options);

		// 분류를 사용할경우 목록 구해와서 세팅
		if($this->module_info->hide_category!='Y')
		{
			// get the user group information
			if(Context::get('is_logged'))
			{
				$group_srls = array_keys($logged_info->group_list);
			}
			else
			{
				$group_srls = array();
			}
			$group_srls_count = count($group_srls);

			// check the grant after obtained the category list
			$oDocumentModel = getModel('document');
			$normal_category_list = $oDocumentModel->getCategoryList($this->module_srl);
			if(count($normal_category_list))
			{
				foreach($normal_category_list as $category_srl => $category)
				{
					$is_granted = TRUE;
					if($category->group_srls)
					{
						$category_group_srls = explode(',',$category->group_srls);
						$is_granted = FALSE;
						if(count(array_intersect($group_srls, $category_group_srls))) $is_granted = TRUE;
					}
					if($is_granted) $category_list[$category_srl] = $category;
				}
			}
			Context::set('category_list', $category_list);
		}

		// 회원 그룹 목록 구해옴
		if($this->module_info->exam_pass_group_option)
		{
			if($this->module_info->exam_pass_group_option>1)
			{
				$group_list = array();
				$default_group_list = $this->module_info->exam_pass_group_list;
				if($default_group_list)
				{
					$oMemberModel = getModel('member');
					$member_group_list = $oMemberModel->getGroups();
					for($i=0;$i<count($default_group_list);$i++)
					{
						if($member_group_list[$default_group_list[$i]]) $group_list[$default_group_list[$i]] = $member_group_list[$default_group_list[$i]]->title;
					}
				}
			} else {
				$group_list = $logged_info->group_list;
			}
		} else {
			$group_list = array();
		}
		// 회원 포인트 정보 구해옴
		if($this->module_info->exam_pass_point_option)
		{
			if($this->module_info->exam_pass_point_option>1)
			{
				$pass_point_min = (int)$this->module_info->exam_pass_point_min;
				$pass_point_max = (int)$this->module_info->exam_pass_point_max;
			} else {
				$oPointModel = getModel('point');
				$pass_point_min = 0;
				$pass_point_max = $oPointModel->getPoint($logged_info->member_srl);
			}
		} else {
			$pass_point_min = $pass_point_max = 0;
		}
		Context::set('group_list', $group_list);
		Context::set('pass_point_min', $pass_point_min);
		Context::set('pass_point_max', $pass_point_max);
		Context::set('bank_question_counts', $examitem->getQuestionBankAvailableCounts());

		// library load
		Context::addJsFile($this->module_path.'tpl/js/jquery.datetimepicker.js');
		Context::addCSSFile($this->module_path.'tpl/css/jquery.datetimepicker.css');
		Context::set('examitem', $examitem);

		$security = new Security();
		$security->encodeHTML('group_list..');
		$security->encodeHTML('join_group_options..');

		$this->setTemplateFile('create');
	}
	/**
	 * 시험지 삭제페이지 출력
	 **/
	public function dispExamDelete()
	{
		// 삭제할 시험지 정보 구해옴
		$document_srl = Context::get('document_srl');
		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($document_srl);

		// 해당 시험지가 존재하는지 체크
		if(!$examitem->isExists())
		{
			return $this->dispExamIndex();
		}

		// 해당 시험지에 대한 권한 체크
		if(!$examitem->isGranted())
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}
		Context::set('examitem',$examitem);

		$this->setTemplateFile('delete');
	}

	/**
	 * 문제 CSV 가져오기 팝업.
	 */
	public function dispExamCsvImport()
	{
		if(($this->module_info->module ?? '') !== 'exam') return $this->stop('msg_invalid_request');
		if(!$this->grant->create)
		{
			return $this->stop('msg_not_permitted');
		}

		$document_srl = (int)Context::get('document_srl');
		$examitem = getModel('exam')->getExam($document_srl);
		if(!$examitem->isExists()) return $this->stop('msg_not_founded');
		if(!$examitem->isGranted()) return $this->stop('msg_not_permitted');
		if((int)$examitem->get('module_srl') !== (int)$this->module_srl) return $this->stop('msg_invalid_request');
		if($examitem->getStatus() === 'Y' && ($this->module_info->lock_published_exam_questions ?? 'Y') === 'Y')
		{
			return $this->stop('msg_published_exam_question_locked');
		}

		Context::set('examitem', $examitem);
		Context::set('question_csv_headers', $this->getQuestionCsvHeaders());
		Context::set('is_question_bank', (int)$examitem->get('page_type') === 2);
		Context::set('question_edit_invalidates_results', (int)$examitem->getJoinCount() > 0);
		Context::set('csv_upload_max_size', ini_get('upload_max_filesize'));
		$this->setLayoutPath($this->module_path.'/skins/'.$this->module_info->skin);
		$this->setLayoutFile('paper_layout');
		$this->setTemplatePath($this->module_path.'tpl');
		Context::addCSSFile($this->module_path.'tpl/css/exam_csv.css');
		$this->setTemplateFile('csv_import');
	}

	/**
	 * Excel에서 바로 열 수 있는 UTF-8 BOM CSV 템플릿을 내려준다.
	 */
	public function dispExamCsvTemplateDownload()
	{
		if(($this->module_info->module ?? '') !== 'exam') return $this->stop('msg_invalid_request');
		if(!$this->grant->create)
		{
			return $this->stop('msg_not_permitted');
		}

		$document_srl = (int)Context::get('document_srl');
		$examitem = getModel('exam')->getExam($document_srl);
		if(!$examitem->isExists()) return $this->stop('msg_not_founded');
		if(!$examitem->isGranted()) return $this->stop('msg_not_permitted');
		if((int)$examitem->get('module_srl') !== (int)$this->module_srl) return $this->stop('msg_invalid_request');

		$rows = array(
			$this->getQuestionCsvHeaders(),
			array('1', '중', '객관식', '', '대한민국의 수도는?', 'N', '', '', '1', '서울', '부산', '대전', '광주', '', '완전일치', '대한민국의 수도는 서울입니다.', '10'),
			array('2', '상', '주관식', '', '대한민국의 수도를 입력하세요.', 'N', '', '', '서울', '', '', '', '', '', '완전일치', '정답은 서울입니다.', '10'),
			array('3', '하', '객관식', '', '다음 중 광역시는?', 'N', '', '', '1,3', '부산', '수원', '대전', '전주', '', '완전일치', '부산과 대전은 광역시입니다.', '10'),
			array('', '', '공통지문', '1~3', '대한민국 수도 공통지문', 'Y', '', '<p>대한민국의 수도와 광역시에 관한 공통 지문입니다.</p>', '', '', '', '', '', '', '', '', '0'),
		);

		$stream = fopen('php://temp', 'w+b');
		fwrite($stream, "\xEF\xBB\xBF");
		foreach($rows as $row) fputcsv($stream, $row, ',', '"', '\\');
		rewind($stream);
		$content = stream_get_contents($stream);
		fclose($stream);

		while(ob_get_level()) @ob_end_clean();
		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="exam_question_template.csv"');
		header('Content-Length: '.strlen($content));
		header('Cache-Control: no-store, no-cache, must-revalidate');
		echo $content;
		exit;
	}
	/**
	 * 시험페이지 출력
	 **/
	private function dispExamPage()
	{
		Context::addMetaTag('viewport', 'width=device-width, initial-scale=1.0');
		$this->setLayoutPath($this->module_path.'/skins/'.$this->module_info->skin);
		$this->setLayoutFile("paper_layout");
		if (!$this->grant->join)
		{
			return $this->stop('msg_not_permitted');
		}
		$document_srl = Context::get('document_srl');
		$mode = Context::get('mode'); // join:응시, 기타:index
		$logged_info = Context::get('logged_info');

		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($document_srl);
		if(!$examitem->isExists())
		{
			Context::set('document_srl','',0);
			return $this->stop('msg_not_founded');
		}
		if($examitem->get('module_srl')!=$this->module_info->module_srl )
		{
			return $this->stop('msg_invalid_request');
		}

		// check the manage grant
		if($this->grant->manager) $examitem->setGrant();
		if($examitem->isSecret() && !$examitem->isGranted())
		{
			return $this->stop('msg_not_founded');
		}
		$can_join_exam_group = $examitem->isJoinGroupAllowed($logged_info);
		Context::set('can_join_exam_group', $can_join_exam_group);
		if($mode === 'join' && !$can_join_exam_group)
		{
			return $this->stop('msg_not_allowed_exam_group');
		}
		Context::set('examitem', $examitem);
		$is_practice = (int)$examitem->get('page_type') === 1;
		$is_question_bank = (int)$examitem->get('page_type') === 2;
		Context::set('is_practice', $is_practice);
		Context::set('is_question_bank', $is_question_bank);
		$display_question_count = $examitem->getDisplayQuestionCount();
		Context::set('display_question_count', $display_question_count);
		Context::set('display_total_point', $is_question_bank ? ($display_question_count ? 100 : 0) : $examitem->getAllQuestionPoint());

		// 이 시험에 응시했던 기록 구해옴
		$resultitem = $oExamModel->getExamResultByDocumentSrl($document_srl,$logged_info->member_srl);
		Context::set('resultitem',$resultitem);
		$join_session = $_SESSION['exam_joinlog'][$examitem->document_srl] ?? null;
		if($join_session && (int)($join_session['member_srl'] ?? 0) !== (int)$logged_info->member_srl)
		{
			unset($_SESSION['exam_joinlog'][$examitem->document_srl]);
			$join_session = null;
		}
		Context::set('has_active_attempt', (bool)$join_session);

		// mode에 따라 체크
		if($mode=="join")
		{
			if($resultitem && $examitem->get('allow_retake') !== 'Y')
			{
				return $this->stop('msg_exists_result');
			}
			// 시험기간인지 체크함
			if($examitem->isDate())
			{
				$now = time();
				if($examitem->get('start_date') && ztime($examitem->get('start_date')) > $now) return $this->stop('msg_not_exam_date');
				if($examitem->get('end_date') && $now > ztime($examitem->get('end_date'))) return $this->stop('msg_not_exam_date');
			}
			$bank_question_order = null;
			if($is_question_bank)
			{
				$bank_question_order = $join_session && array_key_exists('question_order', $join_session)
					? array_values((array)$join_session['question_order'])
					: $examitem->getQuestionBankOrder();
				if(!$bank_question_order) return $this->stop('no_question_list');
			}
			// 응시료가 있을경우 차감함
			if($examitem->get('join_point') && (empty($_SESSION['exam_joinlog'][$examitem->document_srl]) && !$examitem->isGranted()))
			{
				$oPointModel = getModel('point');
				$member_point = $oPointModel->getPoint($logged_info->member_srl);
				if($examitem->get('join_point') > $member_point) return $this->stop('msg_not_enough_point');

				$oPointController = getController('point');
				$oPointController->setPoint($logged_info->member_srl, $examitem->get('join_point'), 'minus');
			}
			// 동일한 응시 세션을 유지하여 응시료 중복 차감 및 제한시간 초기화를 막는다.
			if(!$join_session)
			{
				$_SESSION['exam_joinlog'][$examitem->document_srl] = array(
					"member_srl" => $logged_info->member_srl,
					"start_date" => getInternalDateTime(),
					"start_time" => time(),
					"practice_answers" => array(),
				);
				$join_session = $_SESSION['exam_joinlog'][$examitem->document_srl];
				if($is_question_bank)
				{
					$_SESSION['exam_joinlog'][$examitem->document_srl]['question_order'] = $bank_question_order;
					$join_session['question_order'] = $bank_question_order;
				}
				elseif($examitem->get('random_question_order') === 'Y')
				{
					$question_order = $examitem->getRandomQuestionOrder();
					$_SESSION['exam_joinlog'][$examitem->document_srl]['question_order'] = $question_order;
					$join_session['question_order'] = $question_order;
				}
			}
			elseif($is_question_bank && !array_key_exists('question_order', $join_session))
			{
				$_SESSION['exam_joinlog'][$examitem->document_srl]['question_order'] = $bank_question_order;
				$join_session['question_order'] = $bank_question_order;
			}
			if($is_practice && (!isset($join_session['practice_answers']) || !is_array($join_session['practice_answers'])))
			{
				$_SESSION['exam_joinlog'][$examitem->document_srl]['practice_answers'] = array();
				$join_session['practice_answers'] = array();
			}
			$question_order = $join_session['question_order'] ?? array();
			$remaining_exam_time = 0;
			if(!$is_practice && $examitem->isTime() && $examitem->get('exam_time'))
			{
				$start_time = (int)($join_session['start_time'] ?? ztime($join_session['start_date'] ?? ''));
				$elapsed_time = $start_time ? max(0, time() - $start_time) : 0;
				$remaining_exam_time = max(0, (int)$examitem->get('exam_time') - $elapsed_time);
			}
			Context::set('remaining_exam_time', $remaining_exam_time);
			$questions = $is_question_bank
				? $examitem->getQuestionBankQuestions($question_order)
				: $examitem->getQuestions($question_order);
			if($is_question_bank && !$questions) return $this->stop('no_question_list');
			Context::set('display_question_count', count($questions));
			$submitted_count = 0;
			if($is_practice)
			{
				$practice_answers = $join_session['practice_answers'] ?? array();
				foreach($questions as $qitem)
				{
					$record = $practice_answers[(int)$qitem->question_srl] ?? null;
					if(!$record) continue;
					$qitem->add('practice_graded', 'Y');
					$qitem->add('practice_answer', $record['answer'] ?? '');
					$qitem->add('practice_answer_result', $record['check'] ?? 'X');
					$qitem->add('practice_correct_answer', $record['correct_answer'] ?? '');
					$qitem->add('practice_explanation', $record['explanation'] ?? '');
					$submitted_count++;
				}
			}
			Context::set('practice_submitted_count', $submitted_count);
			Context::set('question_blocks', $examitem->getQuestionBlocksFromQuestions($questions));
		} else {
			Context::set('mode', '');
		}

		// 필요한 library load
		Context::addCSSFile($this->module_path.'tpl/css/jquery.flipcountdown.css');
		Context::addJsFile($this->module_path.'tpl/js/jquery.flipcountdown.js');
		Context::addJsFile($this->module_path.'tpl/js/exam_mobile.js');
		if($is_practice)
		{
			Context::addJsFile($this->module_path.'tpl/js/exam_practice.js');
		}

		// 시험페이지 출력
		$this->setTemplateFile('paper');
	}
	/**
	 * 시험 편집모드/문제 출제(수정) 처리
	 **/
	public function dispExamEditMode()
	{
		$this->setLayoutPath($this->module_path.'/skins/'.$this->module_info->skin);
		$this->setLayoutFile("paper_layout");

		$mode = Context::get('mode'); // write-문제출제및수정
		$document_srl = Context::get('document_srl');
		$question_srl = Context::get('question_srl');
		$logged_info = Context::get('logged_info');

		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($document_srl);
		if(!$examitem->isExists()) return $this->stop('msg_not_founded');
		if(!$examitem->isGranted()) return $this->stop('msg_not_permitted');
		Context::set('examitem', $examitem);
		Context::set('is_question_bank', (int)$examitem->get('page_type') === 2);
		$question_edit_locked = $examitem->getStatus() === 'Y' && $this->module_info->lock_published_exam_questions === 'Y';
		$question_edit_invalidates_results = !$question_edit_locked && (int)$examitem->getJoinCount() > 0;
		Context::set('question_edit_locked', $question_edit_locked);
		Context::set('question_edit_invalidates_results', $question_edit_invalidates_results);
		if($mode === 'write' && $question_edit_locked)
		{
			return $this->stop('msg_published_exam_question_locked');
		}

		// 문제출제 모드일때 필요한 변수들 세팅
		if($mode=="write")
		{
			$questionitem = $oExamModel->getQuestion($question_srl);
			if($question_srl && !$questionitem->isExists()) return $this->stop('msg_not_founded');
			if($questionitem->isExists() && $questionitem->get('document_srl')!=$examitem->document_srl) return $this->stop('msg_invalid_request');
			if((int)$examitem->get('page_type') === 2 && $questionitem->isPassage()) return $this->stop('msg_bank_common_passage_not_supported');
			Context::set('questionitem', $questionitem);
			$passage_range = $questionitem->isPassage() ? $examitem->getPassageRange($question_srl) : array('start' => 0, 'end' => 0);
			Context::set('passage_start', $passage_range['start']);
			Context::set('passage_end', $passage_range['end']);

			// 지문에서 사용할 에디터 로드
			$oEditorModel = getModel('editor');
			$editor = $oEditorModel->getModuleEditor('document', $this->module_srl, $question_srl, 'question_srl', 'q_description_content');
			Context::set('editor', $editor);
		}
		else
		{
			Context::set('question_blocks', $examitem->getQuestionBlocks(array(), true));
		}

		// library load
		Context::addJsFile($this->module_path.'tpl/js/exam_admin.js');

		$this->setTemplateFile('paper_edit');
	}
	/**
	 * 시험 응시현황 출력
	 **/
	public function disExamMyResult()
	{
		// 접근 권한 체크
		$is_logged = Context::get('is_logged');
		if (!$this->grant->access || !$is_logged)
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}
		$logged_info = Context::get('logged_info');

		$args = new StdClass();
		$args->member_srl = $logged_info->member_srl;
		$args->module_srl = $this->module_srl;
		$args->page = Context::get('page');
		$args->list_count = $this->list_count;
		$args->page_count = $this->page_count;
		$args->status = Context::get('search_status');
		$args->exam_status = 'Y';

		// 내가 본 시험 결과 구해옴
		$oExamModel = getModel('exam');
		$output = $oExamModel->getExamResultList($args);
		if(!$output || !$output->toBool())
		{
			return $this->stop($output ? $output->getMessage() : 'msg_invalid_request');
		}

		$category_list = getModel('document')->getCategoryList($this->module_srl);
		foreach(($output->data ?? array()) as $resultitem)
		{
			$resultitem->category_title = isset($category_list[$resultitem->category_srl])
				? $category_list[$resultitem->category_srl]->title
				: '';
		}

		Context::set('result_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		$security = new Security();
		$security->encodeHTML('result_list..title..', 'result_list..category_title');

		$this->setTemplateFile('my_result');
	}
	/**
	 * 시험 채점결과 페이지 출력
	 **/
	public function dispExamCorrectCheck()
	{
		return $this->displayExamResult(false);
	}

	/**
	 * 출제자 또는 관리자 전용 수동채점 페이지 출력
	 */
	public function dispExamManualGrade()
	{
		return $this->displayExamResult(true);
	}

	/**
	 * 일반 결과 화면과 관리용 수동채점 화면을 분리하여 출력한다.
	 */
	private function displayExamResult($manual_grade = false)
	{
		$this->setLayoutPath($this->module_path.'/skins/'.$this->module_info->skin);
		$this->setLayoutFile("paper_layout");

		$log_srl = Context::get('log_srl');
		$logged_info = Context::get('logged_info');

		$oExamModel = getModel('exam');
		$resultitem = $oExamModel->getExamResult($log_srl);
		if(!$resultitem || !$resultitem->member_srl) return $this->stop('msg_invalid_request');
		$examitem = $oExamModel->getExam($resultitem->document_srl);
		if(!$examitem->isExists() || $examitem->get('module_srl') != $this->module_srl) return $this->stop('msg_invalid_request');
		$can_manage_result = $this->grant->manager || $examitem->isGranted();
		if($manual_grade && !$can_manage_result) return $this->stop('msg_not_permitted');
		$can_grade = $manual_grade;
		if($examitem->isSecret() && !$can_manage_result) return $this->stop('msg_not_founded');

		// 응시자, 출제자, 관리자가 아니면 에러
		if(!$can_manage_result && (int)$resultitem->member_srl !== (int)($logged_info->member_srl ?? 0)) return $this->stop('msg_not_permitted');
		$show_result_detail = $can_grade || (Context::get('show_detail') === 'Y' && (int)$resultitem->result_type > 1);

		// 응시자 정보 구해옴
		$oMemberModel = getModel('member');
		$member_info = $oMemberModel->getMemberInfoByMemberSrl($resultitem->member_srl);
		$category = $resultitem->category_srl
			? getModel('document')->getCategory($resultitem->category_srl)
			: null;
		$resultitem->category_title = $category ? $category->title : '';
		$is_question_bank = (int)$examitem->get('page_type') === 2;
		if($is_question_bank)
		{
			$resultitem->answer = $examitem->applyQuestionBankPoints($resultitem->answer ?? array());
		}
		$manual_total_point = 0;
		foreach(($resultitem->answer ?? array()) as $qitem)
		{
			if(!is_object($qitem) || !method_exists($qitem, 'get'))
			{
				continue;
			}
			$manual_total_point += max(0, (int)$qitem->get('point'));
		}

		Context::set('resultitem', $resultitem);
		Context::set('member_info', $member_info);
		Context::set('examitem', $examitem);
		Context::set('can_grade', $can_grade);
		Context::set('show_result_detail', $show_result_detail);
		$clear_exam_draft = !$manual_grade && (int)$resultitem->member_srl === (int)($logged_info->member_srl ?? 0);
		Context::set('clear_exam_draft', $clear_exam_draft);
		if($clear_exam_draft)
		{
			Context::addJsFile($this->module_path.'tpl/js/exam_mobile.js');
		}
		Context::set('result_question_blocks', $examitem->getQuestionBlocksFromQuestions($resultitem->answer ?? array()));
		Context::set('manual_use_question_point', $manual_total_point > 0);
		Context::set('manual_default_score', count($resultitem->answer ?? array()) ? ceil(100 / count($resultitem->answer)) : 0);

		// 시험페이지 출력
		$this->setTemplateFile('paper_correct_check');
	}

	/**
	 * 출제자용 시험별 응시 결과 목록
	 */
	public function dispExamResultList()
	{
		$document_srl = (int)Context::get('document_srl');
		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($document_srl);
		if(!$examitem->isExists() || $examitem->get('module_srl') != $this->module_srl)
		{
			return $this->stop('msg_not_founded');
		}
		if(!$this->grant->manager && !$examitem->isGranted())
		{
			return $this->stop('msg_not_permitted');
		}

		$args = new stdClass();
		$args->document_srl = $document_srl;
		$args->module_srl = $this->module_srl;
		$args->page = Context::get('page');
		$args->list_count = $this->list_count;
		$args->page_count = $this->page_count;
		$args->status = Context::get('search_status');
		$output = $oExamModel->getExamResultList($args);
		if(!$output || !$output->toBool())
		{
			return $this->stop($output ? $output->getMessage() : 'msg_invalid_request');
		}

		$result_list = $output->data ?? array();
		$oMemberModel = getModel('member');
		foreach($result_list as $key => $resultitem)
		{
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($resultitem->member_srl);
			$resultitem->nick_name = $member_info->nick_name ?? '';
			$resultitem->user_name = $member_info->user_name ?? '';
			$resultitem->exam_time_text = $oExamModel->getTimeText($resultitem->exam_time);
			$result_list[$key] = $resultitem;
		}

		Context::set('examitem', $examitem);
		Context::set('result_list', $result_list);
		Context::set('total_count', $output->total_count ?? 0);
		Context::set('page', $output->page ?? 1);
		Context::set('page_navigation', $output->page_navigation ?? null);

		$security = new Security();
		$security->encodeHTML('result_list..user_name', 'result_list..nick_name', 'result_list..title..');
		$this->setTemplateFile('result_list');
	}
	/**
	 * @brief 시험 모듈내 메세지를 출력함
	 * @DEPRECATED This function is deprecated and will be removed at 1.0
	 **/
	function dispExamMessage($msg_code)
	{
		$msg = Context::getLang($msg_code);
		if(!$msg) $msg = $msg_code;
		Context::set('message', $msg);
		$this->setTemplateFile('message');
	}
}
/* End of file exam.view.php */
/* Location: ./modules/exam/exam.view.php */
