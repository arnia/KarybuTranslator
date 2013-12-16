<?php
/**
 * @class  translatorAdminController
 * @author Arnia (info@arnia.ro)
 * @brief  translator module of the admin controller class
 **/

use Symfony\Component\HttpFoundation\Request;

class translatorAdminController extends translator {
    /**
     * @brief Initialization
     **/
    function init() {

    }

    /**
     * @brief proc translator import language variables from file
     * */
    public function procTranslatorAdminImportLangVariables() {
        ini_set('max_execution_time', 0);
        $ds = DIRECTORY_SEPARATOR;

        // Get language
        $dataFile = Context::get('data_file');
        preg_match('/lang_(.*)\.csv/', $dataFile['name'], $langMatch);
        $fileLang = $langMatch[1];

        // Process selected input file
        $request = Request::createFromGlobals();
        foreach($request->files as $uploadedFile) {
            $dir = _KARYBU_PATH_ . 'modules' . $ds . 'translator' . $ds . 'tmp' . $ds;
            $uploadedFile->move($dir, $uploadedFile->getClientOriginalName());
            $fileName = $dir . $uploadedFile->getClientOriginalName();

            // Parse input file
            $rows = $this->loadCSV($fileName);
            foreach ($rows as $row) {
                if (preg_match('/^(common|modules)/',$row[0]))
                    $this->importVarsLang($fileLang, $row);
                elseif (preg_match('/^(layouts|widgets)/',$row[0]))
                    $this->importVarsInfo($fileLang, $row);
            }
        }

        // Redirect back
        header('Location: '. getUrl('act','dispTranslatorAdminContent'));
    }

    /**
     * @brief insert XML value for language in respective file for /common/ and /modules/ dirs
     * */
    private function importVarsLang($lang, $row) {
        if (isset($row[3]) && !empty($row[3])) {
            $cFileName = _KARYBU_PATH_ . $row[0];
            $cVar = $row[1];
            $cStartTrans = $row[2];
            $cEndTrans = $row[3];

            if (!file_exists($cFileName))
                throw new Exception('Language file not found.');
            $items = simplexml_load_file($cFileName);

            $xmlQueryTemplate = 'item[@name="%s"]';
            $selectedXmlQuery = sprintf($xmlQueryTemplate, $cVar);
            $selectedItems = $items->xpath($selectedXmlQuery);
            if (count($selectedItems) > 1)
                throw new Exception("Duplicate item name: $cVar.");
            $selectedItem = $selectedItems[0];

            $emptyQueryTemplate = 'value[@xml:lang="%s"]';
            $emptyXmlQuery = sprintf($emptyQueryTemplate, $lang);
            $emptyItems = $selectedItem->xpath($emptyXmlQuery);
            if (count($emptyItems) > 0) {
                foreach ($emptyItems as $emptyItem) {
                    $domEmptyItem = dom_import_simplexml($emptyItem);
                    $domEmptyItem->parentNode->removeChild($domEmptyItem);
                }
            }

            $node = dom_import_simplexml($selectedItem);
            $no = $node->ownerDocument;

            $child = $no->createElement('value');
            $child->setAttribute('xml:lang', $lang);
            $child->appendChild($no->createCDATASection($cEndTrans));
            $node->appendChild($child);

            $items->saveXML($cFileName);
        }
    }

    /**
     * @brief insert XML value for language in respective file for /layouts/ and /widgets/ dirs
     * */
    private function importVarsInfo($lang, $row) {
        if (isset($row[3]) && !empty($row[3])) {
            $cFileName = _KARYBU_PATH_ . $row[0];
            $cVar = $row[1];
            $cStartTrans = $row[2];
            $cEndTrans = $row[3];

            if ($cStartTrans == 'Image5 caption') {
                if (!file_exists($cFileName))
                    throw new Exception('Language file not found.');
                $info = simplexml_load_file($cFileName);

                $enXmlQueryTemplate = '//*[@xml:lang="en" and text()="%s"]';
                $enXmlQuery = sprintf($enXmlQueryTemplate, $cStartTrans);
                $enInfoItems = $info->xpath($enXmlQuery);
                if (count($enInfoItems) > 1)
                    throw new Exception("Duplicate value: $cStartTrans.");
                $enInfoItem = $enInfoItems[0];
                $parents=$enInfoItem->xpath('..');
                $parent=$parents[0];

                $selXmlQueryTemplate = '//*[@xml:lang="%s"]';
                $selXmlQuery = sprintf($selXmlQueryTemplate, $lang);
                $selInfoItems = $parent->xpath($selXmlQuery);
                if (count($selInfoItems) > 0) {
                    foreach ($selInfoItems as $selInfoItem) {
                        $domInfoItem = dom_import_simplexml($selInfoItem);
                        $domInfoItem->parentNode->removeChild($domInfoItem);
                    }
                }

                $node = dom_import_simplexml($enInfoItem);
                $parentNode = dom_import_simplexml($parent);
                $no = $parentNode->ownerDocument;

                $child = $node->cloneNode();
                $child->setAttribute('xml:lang', $lang);
                // use if you want CDATA
                //$child->appendChild($no->createCDATASection($cEndTrans));
                $child->appendChild($no->createTextNode($cEndTrans));
                $parentNode->appendChild($child);

                $info->saveXML($cFileName);
            }
        }
    }

    /**
     * @brief proc translator export language variables for the selected language
     * */
    public function procTranslatorAdminExportLangVariables() {
        $selectedLangCode = Context::get('txt_language_code');
        $expFile = "lang_$selectedLangCode.csv";

        header('Content-Type: text/csv');
        header("Content-Disposition: attachment;filename=$expFile");
        $fileHandle = fopen('php://output', 'w');

        $ds = DIRECTORY_SEPARATOR;

        //load language from /common
        $xmlFileName = _KARYBU_PATH_ . 'common' . $ds . 'lang' . $ds . 'lang.xml';
        $this->loadUntranslatedLang($fileHandle, $xmlFileName, $selectedLangCode);

        //load language from /modules
        $modulesChildDirs=  glob(_KARYBU_PATH_ . 'modules/*', GLOB_ONLYDIR);
        foreach($modulesChildDirs as $moduleDir){
            $xmlFileName = $moduleDir . $ds . 'lang' . $ds . 'lang.xml';
            $this->loadUntranslatedLang($fileHandle, $xmlFileName, $selectedLangCode);
        }

        //load info from /layouts
        $layoutsChildDirs=  glob(_KARYBU_PATH_ . 'layouts/*', GLOB_ONLYDIR);
        foreach($layoutsChildDirs as $layoutDir){
            $xmlFileName = $layoutDir . $ds . 'conf' . $ds . 'info.xml';
            $this->loadUntranslatedInfo($fileHandle, $xmlFileName, $selectedLangCode);
        }

        //load info from /widgets
        $widgetsChildDirs=  glob(_KARYBU_PATH_ . 'widgets/*', GLOB_ONLYDIR);
        foreach($widgetsChildDirs as $widgetDir){
            $xmlFileName = $widgetDir . $ds . 'conf' . $ds . 'info.xml';
            $this->loadUntranslatedInfo($fileHandle, $xmlFileName, $selectedLangCode);
        }

        fclose($fileHandle);
        exit;
    }

    /*
     * @brief Clean XML duplicates
     */
    public function procTranslatorAdminCleanDuplicates() {
        $ds = DIRECTORY_SEPARATOR;

        //load language from /common
        $xmlFileName = _KARYBU_PATH_ . 'common' . $ds . 'lang' . $ds . 'lang.xml';
        $this->cleanDuplicatesLang($xmlFileName);

        //load language from /modules
        $modulesChildDirs=  glob(_KARYBU_PATH_ . 'modules/*', GLOB_ONLYDIR);
        foreach($modulesChildDirs as $moduleDir){
            $xmlFileName = $moduleDir . $ds . 'lang' . $ds . 'lang.xml';
            $this->cleanDuplicatesLang($xmlFileName);
        }

        /*
        //load info from /layouts
        $layoutsChildDirs=  glob(_KARYBU_PATH_ . 'layouts/*', GLOB_ONLYDIR);
        foreach($layoutsChildDirs as $layoutDir){
            $xmlFileName = $layoutDir . $ds . 'conf' . $ds . 'info.xml';
            $this->cleanDuplicatesInfo($xmlFileName);
        }

        //load info from /widgets
        $widgetsChildDirs=  glob(_KARYBU_PATH_ . 'widgets/*', GLOB_ONLYDIR);
        foreach($widgetsChildDirs as $widgetDir){
            $xmlFileName = $widgetDir . $ds . 'conf' . $ds . 'info.xml';
            $this->cleanDuplicatesInfo($xmlFileName);
        }
        */

        // Redirect back
        header('Location: '. getUrl('act','dispTranslatorAdminContent'));
    }

    private function cleanDuplicatesLang($langFileName){
        if (!file_exists($langFileName))
            return;

        $items = simplexml_load_file($langFileName);
        $selectedXmlQuery = 'value';
        $arr = array();

        // get duplicate nodes to keep
        foreach ($items as $item) {
            $values = $item->xpath($selectedXmlQuery);
            if ($item->attributes()->name)
                $arr[] =  array($item->attributes()->name->__toString() => count($values));
        }
        //$arr2 = array_count_values(array_keys($arr));
        $arr2 = array();
        $arr4 = array();
        foreach ($arr as $elem) $arr2[] = key($elem);
        $arr3 = array_count_values($arr2);
        foreach ($arr3 as $key => $val)
            if ($val > 1)
                foreach ($arr as $elem)
                    if (key($elem) == $key)
                        $arr4[] = $elem;
        if (count($arr4) > 0) {
            $toMod = array();
            foreach ($arr4 as $elem)
                if (!array_key_exists(key($elem), $toMod))
                    $toMod[key($elem)] = $elem[key($elem)];
                else
                    if ($toMod[key($elem)] < $elem[key($elem)])
                        $toMod[key($elem)] = $elem[key($elem)];
        }

        // do the cleaning
        foreach ($items as $item) {
            $values = $item->xpath($selectedXmlQuery);
            if ($item->attributes()->name) {
                $name = $item->attributes()->name->__toString();
                if ((array_key_exists($name, $toMod)) && (count($values) != $toMod[$name])) {
                    //echo "delete duplicate node for item \"$name\"<br>";
                    $domItem = dom_import_simplexml($item);
                    $domItem->parentNode->removeChild($domItem);
                }
            }
        }

        $items->saveXML($langFileName);
    }

    private function cleanDuplicatesInfo($infoFileName){
        return;
        if (!file_exists($infoFileName))
            return;
        $items = simplexml_load_file($infoFileName);
    }

    /*
     * @brief load CSV from file
     */
    private function loadCSV($fileName, $ignoreFirstLine = false) {
        if (($handle = fopen($fileName, "r")) !== false) {
            $rows = array();
            $i = 0;
            while (($data = fgetcsv($handle, null, ",")) !== false) {
                $i++;
                if ($ignoreFirstLine && $i == 1) { continue; }
                $rows[] = $data;
            }
            fclose($handle);
            return $rows;
        } else {
            return false;
        }
    }

    /*
     * @brief load untranslated vars from specified file - common/modules
     */
    private function loadUntranslatedLang(&$fileHandler, $langFileName, $selectedLangCode){
        if (!file_exists($langFileName))
            return;
        $items = simplexml_load_file($langFileName);
        $xmlQueryTemplate = 'value[@xml:lang="%s"]';
        $selectedXmlQuery = sprintf($xmlQueryTemplate, $selectedLangCode);
        $enXmlQuery = sprintf($xmlQueryTemplate, 'en');
        $langFileNameOut = str_replace(_KARYBU_PATH_,'',$langFileName);

        foreach ($items as $item) {
            $enValueArray = $item->xpath($enXmlQuery);
            $selectedLangArray = $item->xpath($selectedXmlQuery);
            if ($enValueArray) {
                if (!$selectedLangArray) {
                    $attrs=$item->attributes();
                    if ($attrs){
                        $variableName=$attrs->name->__toString();
                        $enValue=$enValueArray[0]->__toString();
                        $row=array($langFileNameOut, $variableName, $enValue);
                        fputcsv($fileHandler, $row);
                    }
                } else {
                    $selValue = $selectedLangArray[0]->__toString();
                    if (empty($selValue) || ($selValue == '<![CDATA[]]>')) {
                        $attrs=$item->attributes();
                        if ($attrs){
                            $variableName=$attrs->name->__toString();
                            $enValue=$enValueArray[0]->__toString();
                            $row=array($langFileNameOut, $variableName, $enValue);
                            fputcsv($fileHandler, $row);
                        }
                    }
                }
            }
        }
    }

    /*
     * @brief load untranslated vars from specified file - layout/widgets
     */
    private function loadUntranslatedInfo(&$fileHandler, $infoFileName, $selectedLangCode){
        if (!file_exists($infoFileName))
            return;
        $info = simplexml_load_file($infoFileName);
        $enXmlQuery = '//*[@xml:lang="en"]';
        $enInfoItems = $info->xpath($enXmlQuery);
        $infoFileNameOut = str_replace(_KARYBU_PATH_,'',$infoFileName);

        foreach ($enInfoItems as $item){
            $parents=$item->xpath('..');
            $parent=$parents[0];
            $selectedLangQuery=  sprintf('%s[@xml:lang="%s"]', $item->getName(), $selectedLangCode);
            $selectedLangInfo=$parent->xpath($selectedLangQuery);
            if (!$selectedLangInfo){
                $row=array($infoFileNameOut, $item->getName(), $item->__toString());
                fputcsv($fileHandler, $row);
            } else {
                $selValue = $selectedLangInfo[0]->__toString();
                if (empty($selValue) || ($selValue == '<![CDATA[]]>')) {
                    $row=array($infoFileNameOut, $item->getName(), $item->__toString());
                    fputcsv($fileHandler, $row);
                }
            }
        }
    }

}