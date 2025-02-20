<?php
/**
 * @class  questionItem
 * @author 러키군 (admin@barch.kr)
 * @brief  exam module questionItem class
 */
class questionItem extends BaseObject
{
	/**
	 * question number
	 * @var int
	 */
	var $question_srl = 0;
	var $columnList = array();

	function __construct($question_srl = 0, $columnList = array())
	{
		$this->question_srl = $question_srl;
		$this->columnList = $columnList;
		$this->_loadFromDB();
	}
	function setQuestion($question_srl)
	{
		$this->question_srl = $question_srl;
		$this->_loadFromDB();
	}
	function _loadFromDB()
	{
		if(!$this->question_srl)
		{
			return;
		}

		$args = new stdClass();
		$args->question_srl = $this->question_srl;
		$output = executeQuery('exam.getQuestion', $args, $this->columnList);

		$this->setAttribute($output->data);
	}

	/**
	 * attribute set to Object object
	 * @return void
	 */
	function setAttribute($attribute)
	{
		if(!$attribute->question_srl)
		{
			$this->question_srl = NULL;
			return;
		}

		$this->question_srl = $attribute->question_srl;
		$this->adds($attribute);

		if(count($attribute))
		{
			foreach($attribute as $key => $val)
			{
				$this->{$key} = $val;
			}
		}
	}
	function isExists()
	{
		return $this->question_srl ? TRUE : FALSE;
	}
	function setAccessible()
	{
		$_SESSION['accessibled_question'][$this->question_srl] = TRUE;
	}
	function isDescription()
	{
		return ($this->get('use_description')=='Y')? 'Y' : 'N';
	}
	function isSecret()
	{
		return $this->get('status') == 'Y' ? TRUE : FALSE;
	}
	function isAccessible()
	{
		if($_SESSION['accessibled_question'][$this->question_srl])
		{
			return TRUE;
		}

		$oExamModel = getModel('exam');
		$examitem = $oExamModel->getExam($this->get('document_srl'));
		if($examitem->isGranted())
		{
			$this->setAccessible();
			return TRUE;
		}

		return FALSE;
	}
	function getQLevel()
	{
		return (int)$this->get('question_level');
	}
	function getQType()
	{
		return (int)$this->get('question_type');
	}
	function getTitle()
	{
		return htmlspecialchars(trim($this->get('title')), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}
	function getContent()
	{
		return nl2br(htmlspecialchars(trim($this->get('content')), ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
	}
	function getDescriptionTitle()
	{
		return htmlspecialchars(trim($this->get('description_title')), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}
	function getDescription()
	{
		$description = $this->get('description');
		// rewrite 사용시 경로 변경
		$oContext = &Context::getInstance();
		if($oContext->allow_rewrite)
		{
			$description = preg_replace('/<a([ \t]+)href=("|\')\.\/\?/i',"<a href=\\2". Context::getRequestUri() ."?", $description);
		}
		return $description;
	}
	function getAnswer($i=0)
	{
		$val = ($i)? $this->get('answer'.$i) : $this->get('answer');
		return htmlspecialchars(trim($val), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}
	function getAnswer1()
	{
		return htmlspecialchars(trim($this->get('answer1')), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}
	function getAnswer2()
	{
		return htmlspecialchars(trim($this->get('answer1')), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}
	function getAnswer3()
	{
		return htmlspecialchars(trim($this->get('answer3')), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}
	function getAnswer4()
	{
		return htmlspecialchars(trim($this->get('answer4')), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}
	function getAnswer5()
	{
		return htmlspecialchars(trim($this->get('answer5')), ENT_COMPAT | ENT_HTML401, 'UTF-8', false);
	}
	function getAnswerCount() // 객관식일경우 답이 몇개인지 구해옴
	{
		$list = $this->getAnswerList();
		return count($list);
	}
	function getAnswerList() // 객관식일경우 답이 여러개일수 있으므로 배열로 리턴
	{
		return explode(",", htmlspecialchars(trim($this->get('answer')), ENT_COMPAT | ENT_HTML401, 'UTF-8', false));
	}
	function getRegdate($format = 'Y.m.d H:i:s')
	{
		return zdate($this->get('regdate'), $format);
	}
	function getRegdateTime()
	{
		$regdate = $this->get('regdate');
		$year = substr($regdate, 0, 4);
		$month = substr($regdate, 4, 2);
		$day = substr($regdate, 6, 2);
		$hour = substr($regdate, 8, 2);
		$min = substr($regdate, 10, 2);
		$sec = substr($regdate, 12, 2);
		return mktime($hour, $min, $sec, $month, $day, $year);
	}
	function getRegdateGM()
	{
		return $this->getRegdate('D, d M Y H:i:s') . ' ' . $GLOBALS['_time_zone'];
	}

	function getPoint() {
		return $this->get('point') ? $this->get('point') : 0;
	}
}
/* End of file exam.question.item.php */
/* Location: ./modules/exam/exam.question.item.php */
