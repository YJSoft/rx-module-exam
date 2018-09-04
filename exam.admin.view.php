<?php
/**
 * @class  examAdminView
 * @author 러키군 (admin@barch.kr)
 * @brief  exam module admin view class
 */
class examAdminView extends exam
{
	function init()
	{
		// check module_srl is existed or not
		$module_srl = Context::get('module_srl');
		if(!$module_srl && $this->module_srl) {
			$module_srl = $this->module_srl;
			Context::set('module_srl', $module_srl);
		}

		// generate module model object
		$oModuleModel = getModel('module');

		// get the module infomation based on the module_srl
		if($module_srl) {
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if(!$module_info) {
				Context::set('module_srl','');
				$this->act = 'list';
			} else {
				if($module_info->exam_pass_group_list) $module_info->exam_pass_group_list = explode(",", $module_info->exam_pass_group_list);
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				Context::set('module_info',$module_info);
			}
		}
		if($module_info && $module_info->module != 'exam') return $this->stop("msg_invalid_request");

		// get the module category list
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);

		$security = new Security();
		$security->encodeHTML('module_info.');
		$security->encodeHTML('module_category..');

		// setup template path (board admin panel templates is resided in the tpl folder)
		$template_path = sprintf("%stpl/",$this->module_path);
		$this->setTemplatePath($template_path);

		// install order (sorting) options
		$order_target = array();
		$order_target['list_order'] = Context::getLang('exam_srl');
		foreach($this->order_target as $key) $order_target[$key] = Context::getLang('exam_'.$key);
		Context::set('order_target', $order_target);
	}
	/**
	 * @brief 시험 목록
	 **/
	public function dispExamAdminList()
	{
		$args = new stdClass();
		$args->sort_index = "module_srl";
		$args->page = Context::get('page');
		$args->list_count = 20;
		$args->page_count = 10;
		$args->s_module_category_srl = Context::get('module_category_srl');

		$search_target = Context::get('search_target');
		$search_keyword = Context::get('search_keyword');

		switch ($search_target){
			case 'mid':
				$args->s_mid = $search_keyword;
				break;
			case 'browser_title':
				$args->s_browser_title = $search_keyword;
				break;
		}

		$output = executeQueryArray('exam.getModuleList', $args);
		ModuleModel::syncModuleToSite($output->data);

		// get the skins path
		$oModuleModel = getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);

		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);

		// get the layouts path
		$oLayoutModel = getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);

		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);

		// use context::set to setup variables on the templates
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('exam_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);

		$security = new Security();
		$security->encodeHTML('exam_list..browser_title','exam_list..mid');
		$security->encodeHTML('skin_list..title','mskin_list..title');
		$security->encodeHTML('layout_list..title','layout_list..layout');
		$security->encodeHTML('mlayout_list..title','mlayout_list..layout');

		// 템플릿 파일 지정
		$this->setTemplateFile('exam_list');
	}
	/**
	 * @brief 시험 모듈 추가 및 설정 폼
	 **/
	public function dispExamAdminInsert()
	{
		if(!in_array($this->module_info->module, array('admin', 'exam'))) {
			return $this->stop('msg_invalid_request');
		}

		// get the skins list
		$oModuleModel = getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);

		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);

		// get the layouts list
		$oLayoutModel = getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);

		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);

		// retrieve group list
		$oMemberModel = getModel('member');
		$group_list = $oMemberModel->getGroups();
		Context::set('group_list', $group_list);

		// editor skin list
		$oEditorModel = getModel('editor');
		Context::set('editor_skin_list', $oEditorModel->getEditorSkinList());

		// setup the extra vaiables
		$oExamModel = getModel('exam');
		$extra_vars = $oExamModel->getDefaultListConfig($this->module_info->module_srl);
		$list_config = $oExamModel->getListConfig($this->module_info->module_srl);
		Context::set('extra_vars', $extra_vars);
		Context::set('list_config', $list_config);

		$security = new Security();
		$security->encodeHTML('skin_list..title','mskin_list..title');
		$security->encodeHTML('layout_list..title','layout_list..layout');
		$security->encodeHTML('mlayout_list..title','mlayout_list..layout');
		$security->encodeHTML('group_list..');
		$security->encodeHTML('editor_skin_list..');

		$security->encodeHTML('extra_vars..name','list_config..name');


		// set the template file
		$this->setTemplateFile('exam_insert');
	}
	/**
	 * @brief 시험 모듈 삭제
	 **/
	public function dispExamAdminDelete()
	{
		if(!Context::get('module_srl')) return $this->dispExamAdminList();
		if(!in_array($this->module_info->module, array('admin', 'exam'))) {
			return $this->stop('msg_invalid_request');
		}

		$module_info = Context::get('module_info');
		Context::set('module_info',$module_info);

		$security = new Security();
		$security->encodeHTML('module_info..mid','module_info..module','module_info..document_count');

		// setup the template file
		$this->setTemplateFile('exam_delete');
	}
	/**
	 * @brief 시험 분류 설정
	 **/
	public function dispExamAdminCategoryList()
	{
		$oDocumentModel = getModel('document');
		$category_content = $oDocumentModel->getCategoryHTML($this->module_info->module_srl);
		Context::set('category_content', $category_content);

		Context::set('module_info', $this->module_info);
		$this->setTemplateFile('category_list');
	}

	/**
	 * @brief 권한 설정
	 **/
	function dispExamAdminGrantConfig() {
		$oModuleAdminModel = getAdminModel('module');
		$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);
		$this->setTemplateFile('grant_config');
	}
	/**
	 * @brief 스킨 설정
	 **/
	function dispExamAdminSkinConfig() {
		$oModuleAdminModel = getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);

		$this->setTemplateFile('skin_config');
	}
	/**
	 * @brief 모바일 스킨 설정
	 **/
	public function dispExamAdminMobileSkinConfig()
	{
		$oModuleAdminModel = getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleMobileSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);

		$this->setTemplateFile('skin_config');
	}
	/**
	 * @brief 시험응시 현황
	 **/
	public function dispExamAdminResultList()
	{
		$oExamAdminModel = getAdminModel('exam');
		$output = $oExamAdminModel->getResultList();

		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('result_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);

		$security = new Security();
		$security->encodeHTML('result_list..user_name', 'result_list..nick_name', 'result_list..title..');

		$this->setTemplateFile('result_list');
	}
	/**
	 * @brief 시험 응시현황 기록 수정
	 **/
	public function dispExamAdminResultUpdate()
	{
		if(!in_array($this->module_info->module, array('admin', 'exam'))) {
			return $this->stop('msg_invalid_request');
		}

		// 수정할 기록 정보 구해옴
		$oExamModel = getModel('exam');
		$log_srl = Context::get('log_srl');
		$resultitem = $oExamModel->getExamResult($log_srl);

		if(!$resultitem->log_srl) return $this->stop('msg_not_founded');
		Context::set('resultitem', $resultitem);

		$security = new Security();
		$security->encodeHTML('resultitem..title');

		// set the template file
		$this->setTemplateFile('result_update');
	}
	/**
	 * @brief board module message
	 **/
	public function alertMessage($message)
	{
		$script =  sprintf('<script> xAddEventListener(window,"load", function() { alert("%s"); } );</script>', Context::getLang($message));
		Context::addHtmlHeader( $script );
	}
}
/* End of file exam.admin.view.php */
/* Location: ./modules/exam/exam.admin.view.php */
