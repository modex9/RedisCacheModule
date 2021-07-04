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
     * @var bool Connection status
     */
    public $is_connected = false;

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

    /**
     * CachePhpRedis constructor.
     */
    public function __construct()
    {
        $this->connect();
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

    /**
     * @param $key
     * @param $value
     * @param int $ttl
     * @return false
     */
    private function _set($key, $value)
    {
        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->set($key, $value);
    }

    /**
     * @param $key
     * @return bool
     */
    private function _exists($key)
    {
        if (!$this->is_connected) {
            return false;
        }

        return (bool) $this->get($key);
    }

    /**
     * @param $key
     * @return false
     */
    public function delete($key)
    {
        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->del($key);
    }

    /**
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
        $sql->where("`name` = 'PREDIS_SERVER' OR `name` = 'PREDIS_PORT' OR name = 'PREDIS_AUTH' OR name = 'PREDIS_DB'");
        $params = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql, true, false);
        foreach ($params as $key => $val)
        {
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
        if (!$this->is_connected) {
            return false;
        }

        return $this->redis->get($key);
    }

    /**
     * @param $query
     * @param $result
     * @return bool
     */
    public function setQuery($query, $result)
    {
        if ($this->isBlacklist($query))
            return true;

        if (empty($result) || $result === false)
            $result = array();

        // Store query results in cache
        $key = Tools::encryptIV($query);
        // no need to check the key existence before the set : if the query is already
        // in the cache, setQuery is not invoked
        $this->set($key, $result);

        // Get all table from the query and save them in cache
        if ($tables = $this->getTables($query))
        {
            foreach ($tables as $table)
            {
                if (!$this->redis->hGet($table, $key))
                {
                    $this->adjustTableCacheSize($table);
                    $this->redis->hSet($table, $key, true);
                }
            }
        }
    }

    /**
     * @param $query
     * @return bool
     */
    protected function isBlacklist($query)
    {
        foreach ($this->blacklist as $find)
            if (false !== strpos($query, _DB_PREFIX_.$find))
                return true;
        return false;
    }

    /**
     * @param $key
     * @param $value
     * @param int $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = 0)
    {
        if ($this->_set($key, $value, $ttl))
        {
            return true;
        }
        return false;
    }

    /**
     * @param $query
     */
    public function deleteQuery($query)
    {
        if ($tables = $this->getTables($query))
        {
            foreach ($tables as $table)
            {
                $table_queries = $this->redis->hKeys($table);
                if ($table_queries)
                {
                    foreach ($table_queries as $fs_key)
                    {
                        $this->delete($fs_key);
                        $this->delete($fs_key.'_nrows');
                        $this->redis->hDel($table, $fs_key);
                    }
                }
            }
        }
    }

    /**
     * @param $string
     * @return array|false
     */
    protected function getTables($string)
    {
        if (preg_match_all('/(?:from|join|update|into)\s+`?('._DB_PREFIX_.'[0-9a-z_-]+)(?:`?\s{0,},\s{0,}`?('._DB_PREFIX_.'[0-9a-z_-]+)`?)?(?:`|\s+|\Z)(?!\s*,)/Umsi', $string, $res))
        {
            foreach ($res[2] as $table)
            {
                if ($table != '')
                    $res[1][] = $table;
            }
            return array_unique($res[1]);
        }
        else
            return false;
    }

    /**
     * @param $table
     */
    protected function adjustTableCacheSize($table)
    {
        $table_queries = $this->get($table);
        if ($table_queries && count($table_queries) > 5000)
        {
            // make sure the cache doesn't contains too many elements : delete the first 1000
            $table_buffer = array_slice($table_queries, 0, 1000, true);
            foreach($table_buffer as $fs_key => $value)
            {
                $this->delete($fs_key);
                $this->delete($fs_key.'_nrows');
                $this->redis->hDel($table, $fs_key);
            }
        }
    }
}