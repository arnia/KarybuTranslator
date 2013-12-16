<?php
    /**
     * @class  translatorAdminView
     * @author Arnia (info@arnia.ro)
     * @brief  translator admin view of the module class
     **/
    class translatorAdminView extends translator {
        /**
         * @brief Initialization
         **/
        function init() {
			// Pre-check if module_srl exists. Set module_info if exists
            $module_srl = Context::get('module_srl');
            // Create module model object
            $oModuleModel = &getModel('module');
            // module_srl two come over to save the module, putting the information in advance
            if ($module_srl) {
                $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
                if (!$module_info) {
                    Context::set('module_srl','');
                    $this->act = 'list';
                } else {
                    ModuleModel::syncModuleToSite($module_info);
                    $this->module_info = $module_info;
                    Context::set('module_info',$module_info);
                }
            }
            // Get a list of module categories
            $module_category = $oModuleModel->getModuleCategories();
            Context::set('module_category', $module_category);
			//Security
			$security = new Security();
			$security->encodeHTML('module_category..title');

			// Set template path for admin view pages
            $this->setTemplatePath($this->module_path.'tpl');
		}		
		
		/**
         * @brief Manage a list of translator instances
         **/
        function dispTranslatorAdminContent() {

            Context::set('langs', Context::loadLangSupported());
            Context::set('lang_selected', Context::loadLangSelected());

            /**
             * set template file
             **/
            $this->setTemplateFile('run');
        }

}
?>