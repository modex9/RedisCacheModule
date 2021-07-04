<?php
/**
 * 2015-2016 Michael Dekker
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@michaeldekker.com so we can send you a copy immediately.
 *
 * @author    Michael Dekker <prestashop@michaeldekker.com>
 * @copyright 2015-2016 Michael Dekker
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class PhpRedisCache extends Module
{
    public function __construct()
    {
        $this->name = 'phprediscache';
        $this->tab = 'administration';
        $this->version = '1.4.0';
        $this->author = 'Michael Dekker & Hachem LATRACH';

        parent::__construct();

        $this->displayName = $this->l('Redis Cache (PHP Redis extension)');
        $this->description = $this->l('Use Redis as cache server to give best performance to your shop');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        $this->_checkContent();

        // You'll only benefit from this module if ps version is 1.6.1.0 or higher
        $this->ps_versions_compliancy = array(
            'min' => '1.6.1.0',
            'max' => _PS_VERSION_
        );

        $this->bootstrap = true;
        $this->context->smarty->assign('module_name', $this->name);
    }

    public function install()
    {
        if (!extension_loaded('redis')) {
            Context::getContext()->controller->errors[] = Tools::displayError(
                $this->l('The PHP Redis extension is not available on this server.')
            );
            return false;
        }
        if (!function_exists('preg_match')) {
            Context::getContext()->controller->errors[] = Tools::displayError(
                $this->l('Support for regular expressions (PCRE) is missing on this server.')
            );
            return false;
        }
        if (!parent::install()) {
            return false;
        }
        // Module is installed from now on
        if(!$this->_createContent() ||
            !$this->_copyClass()
        ) {
            $this->uninstall();
            return false;
        }
        if (version_compare(_PS_VERSION_, '1.6.0.0', '>=')) {
            Tools::clearSmartyCache();
            Tools::clearXMLCache();
            Media::clearCache();
            PrestaShopAutoload::getInstance()->generateIndex();
        }

        Configuration::updateValue('PREDIS_SERVER', '127.0.0.1');
        Configuration::updateValue('PREDIS_PORT', '6379');
        Configuration::updateValue('PREDIS_DB', '0');

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() ||
            !$this->_deleteContent()
        ) {
            return false;
        }
        $new_settings = Tools::file_get_contents(_PS_ROOT_DIR_.'/config/settings.inc.php');
        $new_settings = preg_replace(
            '/define\(\'_PS_CACHE_ENABLED_\', \'([01]?)\'\);/Ui',
            'define(\'_PS_CACHE_ENABLED_\', \'0\');',
            $new_settings
        );
        $new_settings = preg_replace(
            '/define\(\'_PS_CACHING_SYSTEM_\', \'(.*?)\'\);/Ui',
            'define(\'_PS_CACHING_SYSTEM_\', \'CacheFs\');',
            $new_settings
        );
        // If there is not settings file modification or if the backup and replacement of the settings file worked
        copy(_PS_ROOT_DIR_.'/config/settings.inc.php', _PS_ROOT_DIR_.'/config/settings.old.php');
        file_put_contents(_PS_ROOT_DIR_.'/config/settings.inc.php', $new_settings);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(_PS_ROOT_DIR_.'/config/settings.inc.php');
        }
        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            Tools::clearSmartyCache();
            Tools::clearXMLCache();
            Media::clearCache();
            Tools::generateIndex();
        }
        $this->_removeClass();

        return true;
    }

    public function getContent()
    {
        $message = '';
        if (class_exists('Redis') && class_exists('CachePhpRedis')) {
            $redis = new Redis();
            $servers = CachePhpRedis::getRedisServer();
        }

        $errors = $this->detectBOSettings();
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $message .= $this->displayError($error);
            }
        }

        if (Tools::isSubmit('flushdb') && Tools::getValue('flushdb') == '1') {
            $flushed = false;
            if (class_exists('Redis') && class_exists('CachePhpRedis')) {
                if ($redis->pconnect($servers['PREDIS_SERVER'], $servers['PREDIS_PORT'])) {
                    $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                    if ($servers['PREDIS_AUTH'] != '' && ($redis->auth((string)$servers['PREDIS_AUTH']))) {
                        $flushed = $redis->flushDB();
                    } else {
                        $flushed = $redis->flushDB();
                    }
                }
            }
            if ($flushed) {
                $message .= $this->displayConfirmation('Databased flushed sucessfully');
            } else {
                $message .= $this->displayError('Unable to flush database');
            }
        }

        if (Tools::isSubmit('submit'.$this->name)) {
            $message .= $this->_saveContent();
        }

        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');


        $settings = Tools::file_get_contents(_PS_ROOT_DIR_.'/config/settings.inc.php');
        if (strpos($settings, 'define(\'_PS_CACHE_ENABLED_\', \'1\');') !== false &&
            strpos($settings, 'define(\'_PS_CACHING_SYSTEM_\', \'CachePhpRedis\');') !== false &&
            class_exists('Redis') && class_exists('CachePhpRedis')) {
            $this->redis = new Redis();

            $servers = CachePhpRedis::getRedisServer();

            if ($this->redis->pconnect($servers['PREDIS_SERVER'], $servers['PREDIS_PORT'])) {
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                if ($servers['PREDIS_AUTH'] != '') {
                    if (!($this->redis->auth((string)$servers['PREDIS_AUTH']))) {
                        $connection_status = $this->l('PrestaShop is NOT connected to Redis');
                    } else {
                        $connection_status = $this->l('PrestaShop is connected to Redis');
                    }
                } else {
                    $connection_status = $this->l('PrestaShop is connected to Redis');
                }
            } else {
                $connection_status = $this->l('PrestaShop is NOT connected to Redis');
            }
        } else {
            $connection_status = $this->l('PrestaShop is NOT connected to Redis');
        }
        // Init Fields form array
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => Translate::getAdminTranslation('Settings', 'AdminReferrers'),
                'icon' => 'icon-cogs'
            ),
            'description' => $connection_status,
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Server'),
                    'name' => 'PREDIS_SERVER',
                    'size' => 200,
                    'required' => true,
                    'desc' => $this->l('The Redis server ip or hostname.')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Port'),
                    'name' => 'PREDIS_PORT',
                    'size' => 200,
                    'required' => true,
                    'desc' => $this->l('The Redis server port.')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Authentication'),
                    'name' => 'PREDIS_AUTH',
                    'size' => 200,
                    'required' => false,
                    'desc' => $this->l('If applicable, enter the auth key.')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Database'),
                    'name' => 'PREDIS_DB',
                    'size' => 200,
                    'required' => false,
                    'desc' => $this->l('Enter the database number.')
                )
            ),
            'submit' => array(
                'title' => Translate::getAdminTranslation('Save', 'AdminReferrers'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Database'),
                'icon' => 'icon-database'
            ),
            'input' => array(
                array(
                    'type' => (version_compare(_PS_VERSION_, '1.6', '>=')) ? 'switch' : 'radio',
                    'class' => (version_compare(_PS_VERSION_, '1.6', '>=')) ? null : 't',
                    'is_bool' => true,
                    'label' => $this->l('I would like to flush the current database: ').$servers['PREDIS_DB'],
                    'name' => 'FLUSHDB',
                    'values' => array(
                        array(
                            'id' => 'flushdb_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'flushdb_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                )
            ),
            'submit' => array(
                'title' => Translate::getAdminTranslation('Save', 'AdminReferrers'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => Translate::getAdminTranslation('Save', 'AdminReferrers'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
                'back' => array(
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&token='.
                        Tools::getAdminTokenLite('AdminModules'),
                    'desc' => Translate::getAdminTranslation('Back to list', 'AdminAttributesGroups')
                )
        );

        $helper->fields_value['PREDIS_SERVER'] = Configuration::get('PREDIS_SERVER');
        $helper->fields_value['PREDIS_PORT'] = Configuration::get('PREDIS_PORT');
        $helper->fields_value['PREDIS_AUTH'] = Configuration::get('PREDIS_AUTH');
        $helper->fields_value['PREDIS_DB'] = Configuration::get('PREDIS_DB');
        $helper->fields_value['FLUSHDB'] = false;

        return $message.$helper->generateForm($fields_form);
    }

    protected function _copyClass()
    {
        $context = Context::getContext();
        $controller = $context->controller;
        if (!file_exists(_PS_OVERRIDE_DIR_.'classes')) {
            if (!@mkdir(_PS_OVERRIDE_DIR_.'classes')) {
                $controller->errors[] = Tools::displayError(
                    sprintf(
                        $this->l('Could not create the directory %s. Please check permissions for the directory %s'),
                        _PS_OVERRIDE_DIR_.'classes',
                        _PS_OVERRIDE_DIR_
                    )
                );
                return false;
            }
        }
        if (!file_exists(_PS_OVERRIDE_DIR_.'classes/cache')) {
            if (!@mkdir(_PS_OVERRIDE_DIR_.'classes/cache')) {
                $controller->errors[] = Tools::displayError(
                    sprintf(
                        $this->l('Could not create the directory %s. Please check permissions for the directory %s'),
                        _PS_OVERRIDE_DIR_.'classes/cache',
                        _PS_OVERRIDE_DIR_.'classes'
                    )
                );
                return false;
            }
        }
        @copy(
            dirname(__FILE__).'/manualoverride/classes/cache/CachePhpRedis.php',
            _PS_OVERRIDE_DIR_.'classes/cache/CachePhpRedis.php'
        );
        $success = (bool)file_exists(_PS_OVERRIDE_DIR_.'classes/cache/CachePhpRedis.php');
        if (!$success) {
            $context->controller->errors[] = Tools::displayError(
                sprintf(
                    $this->l('Could not copy the file %s to %s. Please check the file permissions.'),
                    dirname(__FILE__).'/manualoverride/classes/cache/CachePhpRedis.php',
                    _PS_OVERRIDE_DIR_.'classes/cache/CachePhpRedis.php'
                )
            );
            return false;
        }

        return true;
    }

    protected function _removeClass()
    {
        @unlink(_PS_OVERRIDE_DIR_.'classes/cache/CachePhpRedis.php');
        $success = !file_exists(_PS_OVERRIDE_DIR_.'classes/cache/CachePhpRedis.php');
        if (!$success) {
            Context::getContext()->controller->errors[] = Tools::displayError(
                sprintf(
                    $this->l('Could not remove the file %s. Please check the file permissions.'),
                    _PS_OVERRIDE_DIR_.'classes/cache/CachePhpRedis.php'
                )
            );
            return false;
        }

        return true;
    }

    protected function _saveContent()
    {
        $message = '';

        if ((Validate::isIp2Long(Tools::getValue('PREDIS_SERVER')) ||
                $this->isValidDomain(Tools::getValue('PREDIS_SERVER'))) &&
            Validate::isInt(Tools::getValue('PREDIS_PORT')) &&
            Validate::isInt(Tools::getValue('PREDIS_DB')) &&
            Configuration::updateValue('PREDIS_SERVER', Tools::getValue('PREDIS_SERVER')) &&
            Configuration::updateValue('PREDIS_PORT', Tools::getValue('PREDIS_PORT')) &&
            Configuration::updateValue('PREDIS_AUTH', Tools::getValue('PREDIS_AUTH')) &&
            Configuration::updateValue('PREDIS_DB', Tools::getValue('PREDIS_DB'))
        ) {
            if (get_class(Cache::getInstance()) == 'CachePhpRedis') {
                // Already connected so we need to reconnect here
                $redis = Cache::getInstance();
                $redis->__destruct();
                $redis = Cache::getInstance();
                $redis->connect();
            }
            $message = $this->displayConfirmation($this->l('Your settings have been saved'));
        } else {
            $message = $this->displayError($this->l('There was an error while saving your settings'));
        }

        return $message;
    }

    protected function _checkContent()
    {
        if (!Configuration::get('PREDIS_SERVER') &&
            !Configuration::get('PREDIS_PORT') &&
            !Configuration::get('PREDIS_DB')
        ) {
            $this->warning = $this->l('You need to configure this module.');
        }
    }

    protected function _createContent()
    {
        if (!Configuration::updateValue('PREDIS_SERVER', '') ||
            !Configuration::updateValue('PREDIS_PORT', '') ||
            !Configuration::updateValue('PREDIS_AUTH', '') ||
            !Configuration::updateValue('PREDIS_DB', '0')
        ) {
            return false;
        }

        return true;
    }

    protected function _deleteContent()
    {
        if (!Configuration::deleteByName('PREDIS_SERVER') ||
            !Configuration::deleteByName('PREDIS_PORT') ||
            !Configuration::deleteByName('PREDIS_AUTH') ||
            !Configuration::deleteByName('PREDIS_DB')
        ) {
            return false;
        }

        return true;
    }

    protected function isValidDomain($domain_name)
    {
        return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) //valid chars check
            && preg_match("/^.{1,253}$/", $domain_name) //overall length check
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)   ); //length of each label
    }

    /**
     * Detect Back Office settings
     *
     * @return array Array with error message strings
     */
    protected function detectBOSettings()
    {
        $id_lang = (int)Context::getContext()->language->id;
        $output = array();
        if (Configuration::get('PS_DISABLE_OVERRIDES')) {
            $output[] = $this->l('Overrides are disabled. This module doesn\'t work without overrides. Go to').' "'.
                $this->getTabName('AdminParentTools', $id_lang).
                ' > '.
                $this->getTabName('AdminPerformance', $id_lang).
                '" '.$this->l('and make sure that the option').' "'.
                Translate::getAdminTranslation('Disable all overrides', 'AdminPerformance').
                '" '.$this->l('is set to').' "'.
                Translate::getAdminTranslation('No', 'AdminPerformance').
                '"'.$this->l('.').'<br />';
        }
        if (Configuration::get('PS_DISABLE_NON_NATIVE_MODULE')) {
            $output[] = $this->l('Non native modules such as this one are disabled. Go to').' "'.
                $this->getTabName('AdminParentTools', $id_lang).
                ' > '.
                $this->getTabName('AdminPerformance', $id_lang).
                '" '.$this->l('and make sure that the option').' "'.
                Translate::getAdminTranslation('Disable non PrestaShop modules', 'AdminPerformance').
                '" '.$this->l('is set to').' "'.
                Translate::getAdminTranslation('No', 'AdminPerformance').
                '"'.$this->l('.').'<br />';
        }
        if (!_PS_CACHE_ENABLED_) {
            $output[] = $this->l('Caching has not been enabled. Go to').' "'.
                $this->getTabName('AdminParentPreferences', $id_lang).
                ' > '.
                $this->getTabName('AdminPerformance', $id_lang).
                '" '.$this->l('and make sure that the option').' "'.
                Translate::getAdminTranslation('Use cache', 'AdminPerformance').
                '" '.$this->l('is set to').' "'.
                Translate::getAdminTranslation('Yes', 'AdminPerformance').
                '"'.$this->l('.').'<br />';
        }
        if (_PS_CACHING_SYSTEM_ !== 'CachePhpRedis') {
            $output[] = $this->l('Caching has not been set to CachePhpRedis. Go to').' "'.
                $this->getTabName('AdminParentPreferences', $id_lang).
                ' > '.
                $this->getTabName('AdminPerformance', $id_lang).
                '" '.$this->l('and make sure that the option').' "'.
                Translate::getAdminTranslation('Caching system', 'AdminPerformance').
                '" '.$this->l('is set to').' "CachePhpRedis"'.$this->l('.').'<br />';
        }

        return $output;
    }

    /**
     * Get Tab name from database
     * @param $class string Class name of tab
     * @param $id_lang int Language id
     * @return string Returns the localized tab name
     */
    protected function getTabName($class, $id_lang)
    {
        if (empty($class) || empty($id_lang)) {
            return 'Unknown';
        }

        $sql = new DbQuery();
        $sql->select('tl.`name`');
        $sql->from('tab_lang', 'tl');
        $sql->innerJoin('tab', 't', 't.`id_tab` = tl.`id_tab`');
        $sql->where('t.`class_name` = \''.pSQL($class).'\'');
        $sql->where('tl.`id_lang` = '.(int)$id_lang);

        try {
            return (string)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        } catch (Exception $e) {
            return $this->l('Unknown');
        }
    }
}
