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

/**
 * This class require Redis server Installed
 *
 */
class CachePhpRedis
{
    /**
     * @var RedisClient
     */
    protected $redis;

    /**
     * @var RedisParams
     */
    protected $_params = array();

    /**
     * @var bool Connection status
     */
    public $is_connected = false;

    /**
     * @var array Store list of tables and their associated keys for SQL cache (warning: this var must not be initialized here !)
     */
    protected $sql_tables_cached = null;

    /**
     * @var array List of blacklisted tables for SQL cache, these tables won't be indexed
     */
    protected $blacklist = array(
        'cart',
        'cart_cart_rule',
        'cart_product',
        'connections',
        'connections_source',
        'connections_page',
        'customer',
        'customer_group',
        'customized_data',
        'guest',
        'pagenotfound',
        'page_viewed',
    );

    const SQL_TABLES_NAME = 'tablesCached';

    /**
     * @var array List all keys of cached data and their associated ttl
     */
    protected $keys = array();

    public function __construct()
    {
        $this->connect();

        if ($this->is_connected) {
            $this->keys = @$this->redis->get(_COOKIE_IV_);
            if (!is_array($this->keys)) {
                $this->keys = array();
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Connect to redis server
     */
    public function connect()
    {
        $this->is_connected = false;
        $servers = self::getRedisServer();

        if ($servers) {
            $this->redis = new Redis();

            if ($this->redis->pconnect($servers['PREDIS_SERVER'], $servers['PREDIS_PORT'])) {
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                if ($servers['PREDIS_AUTH'] != '') {
                    if (!($this->redis->auth((string)$servers['PREDIS_AUTH']))) {
                        return;
                    }
                }
                $this->redis->select((int)$servers['PREDIS_DB']);
                $this->is_connected = true;
            }
        }
    }

    protected function _set($key, $value, $ttl = 0)
    {

        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->set($key, $value);
    }

    /**
     * @see Cache::_get()
     *
     * @return bool
     */
    protected function _get($key)
    {
        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->get($key);
    }

    /**
     * @see Cache::_exists()
     *
     * @return bool
     */
    protected function _exists($key)
    {
        if (!$this->is_connected) {
            return false;
        }

        return isset($this->keys[$key]);
    }

    /**
     * @see Cache::_delete()
     *
     * @return bool
     */
    protected function _delete($key)
    {
        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->del($key);
    }

    /**
     * @see Cache::_writeKeys()
     *
     * @return bool
     */
    protected function _writeKeys()
    {
        if (!$this->is_connected) {
            return false;
        }
        $this->redis->set(_COOKIE_IV_, $this->keys);

        return true;
    }

    /**
     * @see Cache::flush()
     *
     * @return bool
     */
    public function flush()
    {
        if (!$this->is_connected) {
            return false;
        }

        return (bool)$this->redis->flushDB();
    }

    /**
     * Close connection to redis server
     *
     * @return bool
     */
    protected function close()
    {
        if (!$this->is_connected) {
            return false;
        }

        // Don't close the connection, needs to be persistent across PHP-sessions
        return true;
    }

    /**
     * Get list of redis server information
     *
     * @return array
     */
    public static function getRedisServer()
    {
        $server = array();
        // bypass the memory fatal error caused functions nesting on PS 1.5
        $sql = new DbQuery();
        $sql->select('`name`, `value`');
        $sql->from('configuration');
        $sql->where('`name` = \'PREDIS_SERVER\' OR `name` = \'PREDIS_PORT\' OR name = \'PREDIS_AUTH\' OR name = \'PREDIS_DB\'');
        $params = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql, true, false);
        foreach ($params as $key => $val) {
            $server[$val['name']] = $val['value'];
        }

        return $server;
    }

    /**
     * Retrieve a data from cache
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        if (!isset($this->keys[$key]))
            return false;

        return $this->_get($key);
    }

    public function setQuery($query, $result)
    {
        if ($this->isBlacklist($query))
            return true;

        if (empty($result) || $result === false)
            $result = array();

        if (is_null($this->sql_tables_cached))
        {
            $this->sql_tables_cached = $this->get(Tools::encryptIV(self::SQL_TABLES_NAME));
            if (!is_array($this->sql_tables_cached))
                $this->sql_tables_cached = array();
        }

        // Store query results in cache
        $key = Tools::encryptIV($query);
        // no need to check the key existence before the set : if the query is already
        // in the cache, setQuery is not invoked
        $this->set($key, $result);

        // Get all table from the query and save them in cache
        if ($tables = $this->getTables($query))
            foreach ($tables as $table)
            {
                if (!isset($this->sql_tables_cached[$table][$key]))
                {
                    $this->adjustTableCacheSize($table);
                    $this->sql_tables_cached[$table][$key] = true;
                }
            }
        $this->set(Tools::encryptIV(self::SQL_TABLES_NAME), $this->sql_tables_cached);
    }

    protected function isBlacklist($query)
    {
        foreach ($this->blacklist as $find)
            if (false !== strpos($query, _DB_PREFIX_.$find))
                return true;
        return false;
    }

    public function set($key, $value, $ttl = 0)
    {
        if ($this->_set($key, $value, $ttl))
        {
            if ($ttl < 0)
                $ttl = 0;

            $this->keys[$key] = ($ttl == 0) ? 0 : time() + $ttl;
            $this->_writeKeys();
            return true;
        }
        return false;
    }

    public function deleteQuery($query)
    {
        if (is_null($this->sql_tables_cached))
        {
            $this->sql_tables_cached = $this->get(Tools::encryptIV(self::SQL_TABLES_NAME));
            if (!is_array($this->sql_tables_cached))
                $this->sql_tables_cached = array();
        }

        if ($tables = $this->getTables($query))
            foreach ($tables as $table)
                if (isset($this->sql_tables_cached[$table]))
                {
                    foreach (array_keys($this->sql_tables_cached[$table]) as $fs_key)
                    {
                        $this->delete($fs_key);
                        $this->delete($fs_key.'_nrows');
                    }
                    unset($this->sql_tables_cached[$table]);
                }
        $this->set(Tools::encryptIV(self::SQL_TABLES_NAME), $this->sql_tables_cached);
    }

    protected function getTables($string)
    {
        if (preg_match_all('/(?:from|join|update|into)\s+`?('._DB_PREFIX_.'[0-9a-z_-]+)(?:`?\s{0,},\s{0,}`?('._DB_PREFIX_.'[0-9a-z_-]+)`?)?(?:`|\s+|\Z)(?!\s*,)/Umsi', $string, $res))
        {
            foreach ($res[2] as $table)
                if ($table != '')
                    $res[1][] = $table;
            return array_unique($res[1]);
        }
        else
            return false;
    }

    protected function adjustTableCacheSize($table)
    {
        if (isset($this->sql_tables_cached[$table])
            && count($this->sql_tables_cached[$table]) > 5000)
        {
            // make sure the cache doesn't contains too many elements : delete the first 1000
            $table_buffer = array_slice($this->sql_tables_cached[$table], 0, 1000, true);
            foreach($table_buffer as $fs_key => $value)
            {
                $this->delete($fs_key);
                $this->delete($fs_key.'_nrows');
                unset($this->sql_tables_cached[$table][$fs_key]);
            }
        }
    }

    public function delete($key)
    {
        // Get list of keys to delete
        $keys = array();
        if ($key == '*')
            $keys = $this->keys;
        elseif (strpos($key, '*') === false)
            $keys = array($key);
        else
        {
            $pattern = str_replace('\\*', '.*', preg_quote($key));
            foreach ($this->keys as $k => $ttl)
                if (preg_match('#^'.$pattern.'$#', $k))
                    $keys[] = $k;
        }

        // Delete keys
        foreach ($keys as $key)
        {
            if (!isset($this->keys[$key]))
                continue;

            if ($this->_delete($key))
                unset($this->keys[$key]);
        }

        $this->_writeKeys();
        return $keys;
    }

}