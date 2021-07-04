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

class AdminPerformanceController extends AdminPerformanceControllerCore
{
    public function initFieldsetCaching()
    {

        if (version_compare(_PS_VERSION_, '1.6.1', '>=')) {
            $phpdoc_langs = array('en', 'zh', 'fr', 'de', 'ja', 'pl', 'ro', 'ru', 'fa', 'es', 'tr');
            $php_lang = in_array($this->context->language->iso_code, $phpdoc_langs) ? $this->context->language->iso_code : 'en';

            $warning_memcached = ' '.$this->l('(you must install the Memcache PECL extension)');

            $warning_apc = ' '.$this->l('(you must install the APC PECL extension');

            $warning_xcache = ' '.$this->l('(you must install the Xcache extension');

            $warning_fs = ' '.sprintf($this->l('(the directory %s must be writable)'), realpath(_PS_CACHEFS_DIRECTORY_));

            $this->fields_form[5]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Caching'),
                    'icon' => 'icon-desktop'
                ),
                'input' => array(
                    array(
                        'type' => 'hidden',
                        'name' => 'cache_up'
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Use cache'),
                        'name' => 'cache_active',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'cache_active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'cache_active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Caching system'),
                        'name' => 'caching_system',
                        'hint' => $this->l('The CacheFS system should be used only when the infrastructure contains one front-end server. If you are not sure, ask your hosting company.'),
                        'values' => array(
                            array(
                                'id' => 'CacheFs',
                                'value' => 'CacheFs',
                                'label' => $this->l('File System').(is_writable(_PS_CACHEFS_DIRECTORY_) ? '' : $warning_fs)
                            ),
                            array(
                                'id' => 'CacheMemcache',
                                'value' => 'CacheMemcache',
                                'label' => $this->l('Memcached').(extension_loaded('memcache') ? '' : $warning_memcached)
                            ),
                            array(
                                'id' => 'CacheApc',
                                'value' => 'CacheApc',
                                'label' => $this->l('APC').(extension_loaded('apc') ? '' : $warning_apc)
                            ),
                            array(
                                'id' => 'CacheXcache',
                                'value' => 'CacheXcache',
                                'label' => $this->l('Xcache').(extension_loaded('xcache') ? '' : $warning_xcache)
                            ),
                            array(
                                'id' => 'CachePhpRedis',
                                'value' => 'CachePhpRedis',
                                'label' => $this->l('CachePhpRedis').(extension_loaded('redis') ? '' : $warning_xcache)
                            ),
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Directory depth'),
                        'name' => 'ps_cache_fs_directory_depth'
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save')
                ),
                'memcachedServers' => true
            );
        } elseif (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $phpdoc_langs = array('en', 'zh', 'fr', 'de', 'ja', 'pl', 'ro', 'ru', 'fa', 'es', 'tr');
            $php_lang = in_array($this->context->language->iso_code, $phpdoc_langs) ? $this->context->language->iso_code : 'en';

            $warning_memcached = ' '.$this->l('(you must install the Memcache PECL extension)');

            $warning_apc = ' '.$this->l('(you must install the APC PECL extension');

            $warning_xcache = ' '.$this->l('(you must install the Xcache extension');

            $warning_fs = ' '.sprintf($this->l('(the directory %s must be writable)'), realpath(_PS_CACHEFS_DIRECTORY_));

            $this->fields_form[6]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Caching'),
                    'icon' => 'icon-desktop'
                ),
                'input' => array(
                    array(
                        'type' => 'hidden',
                        'name' => 'cache_up'
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Use cache'),
                        'name' => 'cache_active',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'cache_active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'cache_active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Caching system'),
                        'name' => 'caching_system',
                        'hint' => $this->l('The CacheFS system should be used only when the infrastructure contains one front-end server. If you are not sure, ask your hosting company.'),
                        'values' => array(
                            array(
                                'id' => 'CacheFs',
                                'value' => 'CacheFs',
                                'label' => $this->l('File System').(is_writable(_PS_CACHEFS_DIRECTORY_) ? '' : $warning_fs)
                            ),
                            array(
                                'id' => 'CacheMemcache',
                                'value' => 'CacheMemcache',
                                'label' => $this->l('Memcached').(extension_loaded('memcache') ? '' : $warning_memcached)
                            ),
                            array(
                                'id' => 'CacheApc',
                                'value' => 'CacheApc',
                                'label' => $this->l('APC').(extension_loaded('apc') ? '' : $warning_apc)
                            ),
                            array(
                                'id' => 'CacheXcache',
                                'value' => 'CacheXcache',
                                'label' => $this->l('Xcache').(extension_loaded('xcache') ? '' : $warning_xcache)
                            ),
                            array(
                                'id' => 'CachePhpRedis',
                                'value' => 'CachePhpRedis',
                                'label' => $this->l('CachePhpRedis').(extension_loaded('redis') ? '' : $warning_xcache)
                            ),
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Directory depth'),
                        'name' => 'ps_cache_fs_directory_depth'
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save')
                ),
                'memcachedServers' => true
            );
        } else {
            $caching_system = array(
                0 => array(
                    'id' => 'CacheMemcache',
                    'name' => $this->l('Memcached')
                ),
                1 => array(
                    'id' => 'CacheApc',
                    'name' => $this->l('APC')
                ),
                2 => array(
                    'id' => 'CacheXcache',
                    'name' => $this->l('Xcache')
                ),
                3 => array(
                    'id' => 'CacheFs',
                    'name' => $this->l('File System')
                ),
                4 => array(
                    'id' => 'CachePhpRedis',
                    'name' => $this->l('CachePhpRedis')
                )
            );

            $this->fields_form[6]['form'] = array(
                'legend' => array(
                    'title' => $this->l('Caching'),
                    'image' => '../img/admin/computer_key.png'
                ),
                'desc' => $this->l('Caching systems are used to speed up your store by caching data into the server\'s memory, avoiding the exhausting task of querying the database.'),
                'input' => array(
                    array(
                        'type' => 'hidden',
                        'name' => 'cache_up'
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Use cache'),
                        'name' => 'active',
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                        'desc' => $this->l('Enable or disable caching system.')
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Caching system'),
                        'name' => 'caching_system',
                        'options' => array(
                            'query' => $caching_system,
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Directory depth'),
                        'name' => 'ps_cache_fs_directory_depth',
                        'size' => 30
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('   Save   '),
                    'class' => 'button'
                ),
                'memcachedServers' => true
            );
        }

        $depth = Configuration::get('PS_CACHEFS_DIRECTORY_DEPTH');
        $this->fields_value['cache_active'] = _PS_CACHE_ENABLED_;
        $this->fields_value['caching_system'] = _PS_CACHING_SYSTEM_;
        $this->fields_value['ps_cache_fs_directory_depth'] = $depth ? $depth : 1;

        $this->tpl_form_vars['servers'] = CacheMemcache::getMemcachedServers();
        $this->tpl_form_vars['_PS_CACHE_ENABLED_'] = _PS_CACHE_ENABLED_;
    }
}
