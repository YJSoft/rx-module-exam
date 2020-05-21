<?php
/**
 * @class  examItem
 * @author 러키군 (admin@barch.kr)
 * @brief  exam module item class
 */
class examItem extends Object
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
	function examItem($document_srl = 0, $columnList = array())
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
		$this->adds($attribute);
		$this->add('passGroupList', $group_list);
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
		return htmlspecialchars($this->get('user_name'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}
	function getNickName()
	{
		return htmlspecialchars($this->get('nick_name'), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
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

		if(count($attrs)) return sprintf("<span style=\"%s\">%s</span>", implode(';',$attrs), htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
		else return htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
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
		return htmlspecialchars($content);
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

		// Replace < , >, "
		$content = str_replace(array('&lt;','&gt;','&quot;','&nbsp;'), array('<','>','"',' '), $content);

		// Delete  a series of whitespaces
		$content = preg_replace('/ ( +)/is', ' ', $content);

		// Truncate string
		$content = trim(cut_str($content, $str_size, $tail));

		// Replace back < , <, "
		$content = str_replace(array('<','>','"'),array('&lt;','&gt;','&quot;'), $content);

		return $content;
	}
	function getExamDate($format = 'Y.m.d H:i')
	{
		if($this->isDate()) $str = zdate($this->get('start_date'),$format).' ~ '.zdate($this->get('end_date'),$format);
		else $str = Context::getLang('exam_no_end_date');
		return $str;
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
	function getQuestions()
	{
		if(!$this->getQuestionCount()) return;
		if(!$this->isGranted() && $this->isSecret()) return;

		// Get a list of comments
		$oExamModel = getModel('exam');
		$output = $oExamModel->getQuestionList($this->document_srl);
		if(!$output->toBool() || !count($output->data)) return;

		$accessible = array();
		$question_list = array();
		$virtual_number = 1;

		foreach($output->data as $key => $val)
		{
			$oQuestionItem = new questionItem();
			$oQuestionItem->setAttribute($val);
			if($this->isGranted()) $accessible[$val->question_srl] = true;
			$question_list[$virtual_number] = $oQuestionItem;
			$virtual_number++;
		}
		return $question_list;
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

		// Check if secret post is
		if($this->isSecret()) $buffs[] = "secret";

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
		return $this->get('total_point');
	}
}
/* End of file exam.item.php */
/* Location: ./modules/exam/exam.item.php */
