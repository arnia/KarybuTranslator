<?php
    /**
     * @class  translator
     * @author Arnia (info@arnia.ro)
     * @brief  base class for translator module 
     **/
    class translator extends ModuleObject {

        /**
         * @brief Actions to be performed on module installation
         **/
        function moduleInstall() {
        }

        /**
         * @brief Checks if the module needs to be updated
         **/
        function checkUpdate() {
            return false;
        }

        /**
         * @brief Updates module
         **/
        function moduleUpdate() {
            return new Object(0,'success_updated');
        }

        /**
         * @brief Re-generates the cache file
         **/
        function recompileCache() {
        }
    }
?>
