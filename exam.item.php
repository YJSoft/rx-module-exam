<?php
/**
 * @class  examItem
 * @author 러키군 (admin@barch.kr)
 * @brief  exam module item class
 */
class examItem extends BaseObject
{
	/**
	 * Document number
	 * @var int
	 */
	var $document_srl = 0;
	/**
	 * column list
	 * @var array
	 */
	var $columnList = array();
	/**
	 * allow script access list
	 * @var array
	 */
	var $allowscriptaccessList = array();
	/**
	 * allow script access key
	 * @var int
	 */
	var $allowscriptaccessKey = 0;
	/**
	 * Constructor
	 * @param int $document_srl
	 * @param array columnList
	 * @return void
	 */
	function __construct($document_srl = 0, $columnList = array())
	{
		$this->document_srl = $document_srl;
		$this->columnList = $columnList;

		$this->_loadFromDB();
	}
	function setExam($document_srl)
	{
		$this->document_srl = $document_srl;
		$this->_loadFromDB();
	}
	/**
	 * Get data from database, and set the value to documentItem object
	 * @param bool $load_extra_vars
	 * @return void
	 */
	function _loadFromDB()
	{
		if(!$this->document_srl) return;

		$exam_item = false;
		$cache_put = false;
		$columnList = array();
		$this->columnList = array();

		// cache controll
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			$cache_key = 'exam_item:' . getNumberingPath($this->document_srl) . $this->document_srl;
			$exam_item = $oCacheHandler->get($cache_key);
			if($exam_item !== false)
			{
				$columnList = array('question_count', 'join_count');
			}
		}

		$args = new stdClass();
		$args->document_srl = $this->document_srl;
		$output = executeQuery('exam.getExam', $args, $columnList);

		if($exam_item === false)
		{
			$exam_item = $output->data;

			//insert in cache
			if($exam_item && $oCacheHandler->isSupport())
			{
				$oCacheHandler->put($cache_key, $exam_item);
			}
		}
		else
		{
			$exam_item->question_count = $output->data->question_count;
			$exam_item->join_count = $output->data->join_count;
		}
		$this->setAttribute($exam_item);
	}
	function setAttribute($attribute)
	{
		if(!$attribute->document_srl)
		{
			$this->document_srl = null;
			return;
		}
		$this->document_srl = $attribute->document_srl;

		// group_list setting
		$group_list = array();
		if($attribute->pass_group_list)
		{
			$oMemberModel = getModel('member');
			$member_group_list = $oMemberModel->getGroups();
			$pass_group_list = explode(",", $attribute->pass_group_list);
			for($i=0;$i<count($pass_group_list);$i++)
			{
				if($member_group_list[$pass_group_list[$i]]) $group_list[$pass_group_list[$i]] = $member_group_list[$pass_group_list[$i]]->title;
			}
		}
		$join_group_list = array();
		if(!empty($attribute->join_group_list))
		{
			$oMemberModel = isset($oMemberModel) ? $oMemberModel : getModel('member');
			$member_group_list = isset($member_group_list) ? $member_group_list : $oMemberModel->getGroups();
			foreach(explode(',', $attribute->join_group_list) as $group_srl)
			{
				if(isset($member_group_list[$group_srl]))
				{
					$join_group_list[$group_srl] = escape($member_group_list[$group_srl]->title, false);
				}
			}
		}
		$this->adds($attribute);
		$this->add('passGroupList', $group_list);
		$this->add('joinGroupList', $join_group_list);
		$GLOBALS['XE_EXAM_LIST'][$this->document_srl] = $this;
	}
	function isExists()
	{
		return $this->document_srl ? true : false;
	}
	function isGranted() // 관리권한이 있거나 작성자일경우 true return
	{
		if($_SESSION['own_exam'][$this->document_srl]) return true;

		if(!Context::get('is_logged')) return false;

		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin == 'Y') return true;

		$oModuleModel = getModel('module');
		$grant = $oModuleModel->getGrant($oModuleModel->getModuleInfoByModuleSrl($this->get('module_srl')), $logged_info);
		if($grant->manager) return true;
		if($this->get('member_srl') && ($this->get('member_srl') == $logged_info->member_srl)) return true;

		return false;
	}
	function setGrant()
	{
		$_SESSION['own_exam'][$this->document_srl] = true;
	}
	function isAccessible()
	{
		return $_SESSION['accessible'][$this->document_srl]==true?true:false;
	}
	function isEditable()
	{
		if($this->isGranted() || !$this->get('member_srl')) return true;
		return false;
	}
	function isSecret()
	{
		return $this->get('status') == 'N' ? true : false;
	}
	function isDate()
	{
		return $this->get('is_date') == 'Y' ? true : false;
	}
	function isTime()
	{
		return $this->get('is_time') == 'Y' ? true : false;
	}
	function isPassGroup($key)
	{
		if(!$this->get('passGroupList')) return false;
		if(array_key_exists($key, $this->get('passGroupList'))) return true;

		return false;
	}
	function isJoinGroup($key)
	{
		$join_group_list = $this->get('joinGroupList');
		return is_array($join_group_list) && array_key_exists($key, $join_group_list);
	}
	function isJoinGroupAllowed($logged_info = null)
	{
		$join_group_list = $this->get('joinGroupList');
		if(!is_array($join_group_list) || !count($join_group_list)) return true;
		if($this->isGranted()) return true;
		if(!$logged_info) $logged_info = Context::get('logged_info');
		if(!$logged_info || empty($logged_info->group_list)) return false;

		return count(array_intersect(array_keys($join_group_list), array_keys((array)$logged_info->group_list))) > 0;
	}
	function doCart()
	{
		if(!$this->document_srl) return false;
		if($this->isCarted()) $this->removeCart();
		else $this->addCart();
	}
	function addCart()
	{
		$_SESSION['exam_management'][$this->document_srl] = true;
	}
	function removeCart()
	{
		unset($_SESSION['exam_management'][$this->document_srl]);
	}
	function isCarted()
	{
		return $_SESSION['exam_management'][$this->document_srl];
	}
	function getIpAddress()
	{
		if($this->isGranted())
		{
			return $this->get('ipaddress');
		}

		return '*' . strstr($this->get('ipaddress'), '.');
	}
	function getMemberSrl()
	{
		return $this->get('member_srl');
	}
	function getUserName()
	{
		return escape($this->get('user_name'), false);
	}
	function getNickName()
	{
		return escape($this->get('nick_name'), false);
	}
	// 시간치환 초를 시간으로 변환하는 함수
	function getTimeText() {
		$time = $this->get('exam_time');
		$oExamModel = getModel('exam');
		return $oExamModel->getTimeText($time);
	 }
	function getTitleText($cut_size = 0, $tail='...')
	{
		if(!$this->document_srl) return;

		if($cut_size) $title = cut_str($this->get('title'), $cut_size, $tail);
		else $title = $this->get('title');

		return $title;
	}
	function getTitle($cut_size = 0, $tail='...')
	{
		if(!$this->document_srl) return;

		$title = $this->getTitleText($cut_size, $tail);

		$attrs = array();
		$this->add('title_color', trim($this->get('title_color')));
		if($this->get('title_bold')=='Y') $attrs[] = "font-weight:bold;";
		if($this->get('title_color') && $this->get('title_color') != 'N') $attrs[] = "color:#".$this->get('title_color');

		if(count($attrs)) return sprintf("<span style=\"%s\">%s</span>", implode(';',$attrs), escape($title, false));
		else return escape($title, false);
	}
	function getContentText($strlen = 0)
	{
		if(!$this->document_srl) return;
		if($this->isSecret() && !$this->isGranted() && !$this->isAccessible()) return Context::getLang('msg_is_secret');

		$result = $this->_checkAccessibleFromStatus();
		if($result) $_SESSION['accessible'][$this->document_srl] = true;

		$content = $this->get('content');
		$content = preg_replace_callback('/<(object|param|embed)[^>]*/is', array($this, '_checkAllowScriptAccess'), $content);
		$content = preg_replace_callback('/<object[^>]*>/is', array($this, '_addAllowScriptAccess'), $content);

		if($strlen) return cut_str(strip_tags($content),$strlen,'...');
		return escape($content);
	}
	function _addAllowScriptAccess($m)
	{
		if($this->allowscriptaccessList[$this->allowscriptaccessKey] == 1)
		{
			$m[0] = $m[0].'<param name="allowscriptaccess" value="never"></param>';
		}
		$this->allowscriptaccessKey++;
		return $m[0];
	}
	function _checkAllowScriptAccess($m)
	{
		if($m[1] == 'object')
		{
			$this->allowscriptaccessList[] = 1;
		}

		if($m[1] == 'param')
		{
			if(stripos($m[0], 'allowscriptaccess'))
			{
				$m[0] = '<param name="allowscriptaccess" value="never"';
				if(substr($m[0], -1) == '/')
				{
					$m[0] .= '/';
				}
				$this->allowscriptaccessList[count($this->allowscriptaccessList)-1]--;
			}
		}
		else if($m[1] == 'embed')
		{
			if(stripos($m[0], 'allowscriptaccess'))
			{
				$m[0] = preg_replace('/always|samedomain/i', 'never', $m[0]);
			}
			else
			{
				$m[0] = preg_replace('/\<embed/i', '<embed allowscriptaccess="never"', $m[0]);
			}
		}
		return $m[0];
	}
	function getContent()
	{
		if(!$this->document_srl) return;

		if($this->isSecret() && !$this->isGranted() && !$this->isAccessible()) return Context::getLang('msg_is_secret');

		$result = $this->_checkAccessibleFromStatus();
		if($result) $_SESSION['accessible'][$this->document_srl] = true;

		$content = $this->get('content');
		if(!$stripEmbedTagException) stripEmbedTagForAdmin($content, $this->get('member_srl'));

		// Define a link if using a rewrite module
		$oContext = &Context::getInstance();
		if($oContext->allow_rewrite)
		{
			$content = preg_replace('/<a([ \t]+)href=("|\')\.\/\?/i',"<a href=\\2". Context::getRequestUri() ."?", $content);
		}
		return $content;
	}
	/**
	 * Return transformed content by Editor codes
	 * @return string
	 */
	function getTransContent()
	{
		$oEditorController = getController('editor');

		$content = $this->getContent();
		$content = $oEditorController->transComponent($content);

		return $content;
	}
	function getSummary($str_size = 50, $tail = '...')
	{
		$content = $this->getContent();

		// For a newlink, inert a whitespace
		$content = preg_replace('!(<br[\s]*/{0,1}>[\s]*)+!is', ' ', $content);

		// Replace tags such as </p> , </div> , </li> and others to a whitespace
		$content = str_replace(array('</p>', '</div>', '</li>'), ' ', $content);

		// Remove Tags
		$content = preg_replace('!<([^>]*?)>!is','', $content);

		// Convert HTML entities temporarily for truncation.
		$content = html_entity_decode($content, ENT_QUOTES);

		// Delete  a series of whitespaces
		$content = preg_replace('/ ( +)/is', ' ', $content);

		// Truncate string
		$content = trim(cut_str($content, $str_size, $tail));

		return escape($content, false);
	}
	function getExamDate($format = 'Y.m.d H:i', $short = FALSE)
	{
		if(!$this->isDate())
		{
			return Context::getLang('exam_no_end_date');
		}

		$start_date = $this->get('start_date');
		$end_date = $this->get('end_date');
		if(!$start_date && !$end_date)
		{
			return Context::getLang('exam_no_end_date');
		}
		if(!$short)
		{
			$start_text = $start_date ? zdate($start_date, $format) : '';
			$end_text = $end_date ? zdate($end_date, $format) : '';
			return $start_date && $end_date ? $start_text . ' ~ ' . $end_text : ($start_date ? $start_text . ' ~' : '~ ' . $end_text);
		}

		// 비교와 표시 모두 현재 사용자의 시간대를 기준으로 처리한다.
		$start_local = $start_date ? zdate($start_date, 'YmdHi') : '';
		$end_local = $end_date ? zdate($end_date, 'YmdHi') : '';
		$current_year = zdate(getInternalDateTime(), 'Y');
		if(!$start_date || !$end_date)
		{
			$target_date = $start_date ?: $end_date;
			$target_local = $start_local ?: $end_local;
			$target_format = substr($target_local, 10, 2) === '00' ? 'm.d H시' : 'm.d H시i분';
			if(substr($target_local, 0, 4) !== $current_year) $target_format = 'Y.' . $target_format;
			$target_text = zdate($target_date, $target_format);
			return $start_date ? $target_text . ' ~' : '~ ' . $target_text;
		}

		$this_year = substr($start_local, 0, 4) === $current_year && substr($end_local, 0, 4) === $current_year;
		$on_the_hour = substr($start_local, 10, 2) === '00' && substr($end_local, 10, 2) === '00';
		$start_format = ($this_year ? '' : 'Y.') . ($on_the_hour ? 'm.d H시' : 'm.d H시i분');
		$start_text = zdate($start_date, $start_format);
		if($start_local === $end_local) return $start_text;

		if($on_the_hour)
		{
			if(substr($start_local, 0, 8) === substr($end_local, 0, 8)) $end_format = 'H시';
			elseif(substr($start_local, 0, 4) === substr($end_local, 0, 4)) $end_format = 'm.d H시';
			else $end_format = 'Y.m.d H시';
		}
		else
		{
			if(substr($start_local, 0, 10) === substr($end_local, 0, 10)) $end_format = 'i분';
			elseif(substr($start_local, 0, 8) === substr($end_local, 0, 8)) $end_format = 'H시i분';
			elseif(substr($start_local, 0, 4) === substr($end_local, 0, 4)) $end_format = 'm.d H시i분';
			else $end_format = 'Y.m.d H시i분';
		}
		return $start_text . ' ~ ' . zdate($end_date, $end_format);
	}
	function getRegdate($format = 'Y.m.d H:i:s')
	{
		return zdate($this->get('regdate'), $format);
	}
	function getRegdateTime()
	{
		$regdate = $this->get('regdate');
		$year = substr($regdate,0,4);
		$month = substr($regdate,4,2);
		$day = substr($regdate,6,2);
		$hour = substr($regdate,8,2);
		$min = substr($regdate,10,2);
		$sec = substr($regdate,12,2);
		return mktime($hour,$min,$sec,$month,$day,$year);
	}

	function getRegdateGM()
	{
		return $this->getRegdate('D, d M Y H:i:s').' '.$GLOBALS['_time_zone'];
	}
	function getRegdateDT()
	{
		return $this->getRegdate('Y-m-d').'T'.$this->getRegdate('H:i:s').substr($GLOBALS['_time_zone'],0,3).':'.substr($GLOBALS['_time_zone'],3,2);
	}
	function getUpdate($format = 'Y.m.d H:i:s')
	{
		return zdate($this->get('last_update'), $format);
	}
	function getUpdateTime()
	{
		$year = substr($this->get('last_update'),0,4);
		$month = substr($this->get('last_update'),4,2);
		$day = substr($this->get('last_update'),6,2);
		$hour = substr($this->get('last_update'),8,2);
		$min = substr($this->get('last_update'),10,2);
		$sec = substr($this->get('last_update'),12,2);
		return mktime($hour,$min,$sec,$month,$day,$year);
	}
	function getUpdateGM()
	{
		return gmdate("D, d M Y H:i:s", $this->getUpdateTime());
	}
	function getUpdateDT()
	{
		return $this->getUpdate('Y-m-d').'T'.$this->getUpdate('H:i:s').substr($GLOBALS['_time_zone'],0,3).':'.substr($GLOBALS['_time_zone'],3,2);
	}
	function getPermanentUrl()
	{
		return getFullUrl('','mid',$this->getExamMid(),'document_srl',$this->get('document_srl'));
	}
	/**
	 * Update join count
	 * @return void
	 */
	function updateJoinCount()
	{
		$oExamController = getController('exam');
		if($oExamController->updateJoinCount($this))
		{
			$join_count = $this->get('join_count');
			$this->add('join_count', $join_count+1);
		}
	}
	function getQuestionCount()
	{
		return (int)$this->get('question_count');
	}
	function getJoinCount()
	{
		return $this->get('join_count');
	}
	function getJoinPoint()
	{
		return (int)$this->get('join_point');
	}
	function getPassPoint()
	{
		return (int)$this->get('pass_point');
	}
	/**
	 * 시험 문제 목록을 지정된 순서로 반환한다.
	 *
	 * @param array $question_order 문제 번호(question_srl) 배열
	 * @return array|null
	 */
	function getQuestions($question_order = array())
	{
		if(!$this->getQuestionCount()) return array();
		if(!$this->isGranted() && $this->isSecret()) return;
		$question_order = is_array($question_order) ? $question_order : array();

		$question_items = array();
		foreach($this->getAllQuestionItems() as $oQuestionItem)
		{
			if(!$oQuestionItem->isPassage())
			{
				$question_items[(int)$oQuestionItem->question_srl] = $oQuestionItem;
			}
		}

		if($question_order)
		{
			$ordered_items = array();
			foreach($question_order as $question_srl)
			{
				$question_srl = (int)$question_srl;
				if(isset($question_items[$question_srl]))
				{
					$ordered_items[$question_srl] = $question_items[$question_srl];
				}
			}
			$question_items = $ordered_items;
		}

		// 공통 지문에 연결된 문제는 수동 순서 변경 후에도 하나의 연속된 묶음으로 유지한다.
		$grouped_items = array();
		$used_passages = array();
		foreach($question_items as $question_srl => $oQuestionItem)
		{
			$passage_srl = (int)$oQuestionItem->get('passage_srl');
			if(!$passage_srl)
			{
				$grouped_items[$question_srl] = $oQuestionItem;
				continue;
			}
			if(isset($used_passages[$passage_srl])) continue;

			foreach($question_items as $group_question_srl => $group_question_item)
			{
				if((int)$group_question_item->get('passage_srl') === $passage_srl)
				{
					$grouped_items[$group_question_srl] = $group_question_item;
				}
			}
			$used_passages[$passage_srl] = true;
		}
		$question_items = $grouped_items;

		$question_list = array();
		$virtual_number = 1;
		foreach($question_items as $oQuestionItem)
		{
			$question_list[$virtual_number++] = $oQuestionItem;
		}
		return $question_list;
	}

	/**
	 * 공통 지문을 포함한 모든 출제 항목을 반환한다.
	 *
	 * @return array
	 */
	private function getAllQuestionItems()
	{
		$is_granted = $this->isGranted();
		if(!$is_granted && $this->isSecret()) return array();

		$oExamModel = getModel('exam');
		$output = $oExamModel->getQuestionList($this->document_srl);
		if(!$output || !$output->toBool() || !count($output->data ?? array()))
		{
			return array();
		}

		$items = array();
		foreach($output->data as $val)
		{
			$oQuestionItem = new questionItem();
			$oQuestionItem->setAttribute($val);
			if($is_granted) $oQuestionItem->setAccessible();
			$items[] = $oQuestionItem;
		}
		return $items;
	}

	/**
	 * 공통 지문 목록을 question_srl 기준으로 반환한다.
	 *
	 * @return array
	 */
	function getPassages()
	{
		$passages = array();
		foreach($this->getAllQuestionItems() as $oQuestionItem)
		{
			if($oQuestionItem->isPassage())
			{
				$passages[(int)$oQuestionItem->question_srl] = $oQuestionItem;
			}
		}
		return $passages;
	}

	/**
	 * 문제 목록을 공통 지문 단위의 블록으로 묶는다.
	 *
	 * @param array $questions 번호가 유지된 문제 목록
	 * @param bool $include_orphan_passages 연결된 문제가 없는 공통 지문 포함 여부
	 * @return array
	 */
	function getQuestionBlocksFromQuestions($questions, $include_orphan_passages = false)
	{
		$questions = is_array($questions) ? $questions : array();
		$passages = $this->getPassages();
		$used_passages = array();
		$blocks = array();

		foreach($questions as $no => $qitem)
		{
			if(!is_object($qitem) || !method_exists($qitem, 'get')) continue;

			$passage_srl = (int)$qitem->get('passage_srl');
			if($passage_srl && isset($passages[$passage_srl]))
			{
				if(isset($used_passages[$passage_srl])) continue;
				$block_questions = array();
				foreach($questions as $question_no => $question_item)
				{
					if(is_object($question_item) && method_exists($question_item, 'get') && (int)$question_item->get('passage_srl') === $passage_srl)
					{
						$block_questions[$question_no] = $question_item;
					}
				}
				$blocks[] = (object)array(
					'passage' => $passages[$passage_srl],
					'questions' => $block_questions,
					'start' => $block_questions ? array_key_first($block_questions) : 0,
					'end' => $block_questions ? array_key_last($block_questions) : 0,
				);
				$used_passages[$passage_srl] = true;
			}
			else
			{
				$blocks[] = (object)array(
					'passage' => null,
					'questions' => array($no => $qitem),
					'start' => $no,
					'end' => $no,
				);
			}
		}

		if($include_orphan_passages)
		{
			foreach($passages as $passage_srl => $passage)
			{
				if(isset($used_passages[$passage_srl])) continue;
				$blocks[] = (object)array(
					'passage' => $passage,
					'questions' => array(),
					'start' => 0,
					'end' => 0,
				);
			}
		}

		return $blocks;
	}

	function getQuestionBlocks($question_order = array(), $include_orphan_passages = false)
	{
		return $this->getQuestionBlocksFromQuestions($this->getQuestions($question_order), $include_orphan_passages);
	}

	/**
	 * 공통 지문 단위로 섞은 문제 순서를 반환한다.
	 *
	 * @return array
	 */
	function getRandomQuestionOrder()
	{
		$blocks = $this->getQuestionBlocks();
		shuffle($blocks);
		$question_order = array();
		foreach($blocks as $block)
		{
			// 공통 지문과 연결된 문제는 한 묶음으로 유지하되 묶음 내부 순서도 섞는다.
			$block_questions = array_values($block->questions);
			shuffle($block_questions);
			foreach($block_questions as $qitem)
			{
				$question_order[] = (int)$qitem->question_srl;
			}
		}
		return $question_order;
	}

	/**
	 * 공통 지문에 연결된 현재 문제번호 범위를 반환한다.
	 *
	 * @param int $passage_srl
	 * @return array
	 */
	function getPassageRange($passage_srl)
	{
		$range = array('start' => 0, 'end' => 0);
		foreach($this->getQuestions() as $no => $qitem)
		{
			if((int)$qitem->get('passage_srl') !== (int)$passage_srl) continue;
			if(!$range['start']) $range['start'] = $no;
			$range['end'] = $no;
		}
		return $range;
	}

	/**
	 * new,update 등의 아이콘정보 세팅
	 * @param int $time_interval
	 * @return array
	 */
	function getExtraImages($time_interval = 43200)
	{
		if(!$this->document_srl) return;
		// variables for icon list
		$buffs = array();

		$check_files = false;

		// Set the latest time
		$time_check = date("YmdHis", $_SERVER['REQUEST_TIME']-$time_interval);

		// Check new post
		if($this->get('regdate')>$time_check) $buffs[] = "new";
		else if($this->get('last_update')>$time_check) $buffs[] = "update";

		return $buffs;
	}
	function getStatus()
	{
		if(!$this->get('status')) return $this->getConfigStatus('',true);
		return $this->get('status');
	}
	function getStatusText()
	{
		if(!$this->get('status')) return $this->getConfigStatus('');
		return $this->getConfigStatus($this->get('status'));
	}
	/**
	 * new,update 등의 아이콘을 붙여 html형태로 리턴
	 * @param int $time_check
	 * @return string
	 */
	function printExtraImages($time_check = 43200)
	{
		if(!$this->document_srl) return;
		// Get the icon directory
		$path = sprintf('%s%s',getUrl(), 'modules/exam/tpl/icons/');

		$buffs = $this->getExtraImages($time_check);
		if(!count($buffs)) return;

		$buff = array();
		foreach($buffs as $key => $val)
		{
			$buff[] = sprintf('<img src="%s%s.gif" alt="%s" title="%s" style="margin-right:2px;" />', $path, $val, $val, $val);
		}
		return implode('', $buff);
	}
	/**
	 * Check accessible by document status
	 * @param array $matches
	 * @return mixed
	 */
	function _checkAccessibleFromStatus()
	{
		$logged_info = Context::get('logged_info');
		if($logged_info->is_admin == 'Y') return true;

		$status = $this->get('status');
		if(empty($status)) return false;

		if($status == 'Y') return true;
		return false;
	}
	/**
	 * Returns the document's mid in order to construct SEO friendly URLs
	 * @return string
	 */
	function getExamMid()
	{
		$model = getModel('module');
		$module = $model->getModuleInfoByModuleSrl($this->get('module_srl'));
		return $module->mid;
	}
	/**
	 * Returns the document's actual title (browser_title)
	 * @return string
	 */
	function getModuleName()
	{
		$model = getModel('module');
		$module = $model->getModuleInfoByModuleSrl($this->get('module_srl'));
		return $module->browser_title;
	}
	
	function getBrowserTitle()
	{
		return $this->getModuleName();
	}

	function getAllQuestionPoint()
	{
		$total_point = (int)$this->get('total_point');
		if($total_point > 0) return $total_point;

		// 이전 버전에서 시험지 설정을 수정하며 총점 캐시가 0으로 초기화된 경우,
		// 실제 문항에 저장된 배점을 사용하여 100점 자동 배점으로 잘못 처리되지 않게 한다.
		foreach(($this->getQuestions() ?: array()) as $qitem)
		{
			$total_point += max(0, (int)$qitem->getPoint());
		}
		if($total_point > 0) $this->add('total_point', $total_point);
		return $total_point;
	}
}
/* End of file exam.item.php */
/* Location: ./modules/exam/exam.item.php */
