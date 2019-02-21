<?php
/**
 * pukoframework.
 * MVC PHP Framework for quick and fast PHP Application Development.
 * Copyright (c) 2016, Didit Velliz
 *
 * @author Didit Velliz
 * @link https://github.com/velliz/pukoframework
 * @since Version 0.9.1
 */

namespace pukoframework\pda;

use PDOException;
use Memcached;
use PDO;
use pukoframework\config\Config;
use pukoframework\log\LogTransforms;

/**
 * Class DBI
 * @package pukoframework\pda
 */
class DBI
{

    use LogTransforms;

    private static $dbi;

    protected $query;
    protected $queryParams;

    protected $dbType;
    protected $dbName;

    private $username;
    private $password;

    private $host;
    private $port;

    /**
     * @var bool
     */
    private $cache = false;

    private $queryPattern = '#@([0-9]+)#';

    /**
     * @param $connection array
     */
    protected function DBISet($connection)
    {
        $this->dbType = $connection['dbType'];
        $this->host = $connection['host'];
        $this->port = $connection['port'];
        $this->dbName = $connection['dbName'];
        $this->username = $connection['user'];
        $this->password = $connection['pass'];
        $this->cache = $connection['cache'];
    }

    /**
     * DBI constructor.
     * @param $query
     * @throws \Exception
     */
    protected function __construct($query)
    {
        $this->query = $query;
        if (is_object(self::$dbi)) {
            return;
        }

        $this->DBISet(Config::Data('database'));
        $pdoConnection = "$this->dbType:host=$this->host;port=$this->port;dbname=$this->dbName";

        try {
            self::$dbi = new PDO($pdoConnection, $this->username, $this->password);
            self::$dbi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $ex) {
            $this->notify('Connection failed: ' . $ex->getMessage(), $ex->getTrace());
        }
    }

    /**
     * @param $query string
     * @return DBI
     * @throws \Exception
     */
    public static function Prepare($query)
    {
        return new DBI($query);
    }

    /**
     * @param $array
     * @param bool $hasBinary
     * @return bool|string
     * @throws \Exception
     */
    public function Save($array, $hasBinary = false)
    {
        $keys = $values = array();
        $insert_text = "INSERT INTO $this->query";
        foreach ($array as $k => $v) {
            $keys[] = $k;
            $values[] = $v;
        }
        $key_string = "(";
        foreach ($keys as $key) {
            $key_string = $key_string . "`" . $key . "`, ";
        }
        $key_string = substr($key_string, 0, -2);
        $insert_text = $insert_text . " " . $key_string . ")";
        $insert_text = $insert_text . " VALUES ";
        $value_string = "(";
        foreach ($keys as $key) {
            $value_string = $value_string . ":" . $key . ", ";
        }
        $value_string = substr($value_string, 0, -2);
        $insert_text = $insert_text . $value_string . ");";

        try {
            $statement = self::$dbi->prepare($insert_text);
            foreach ($keys as $no => $key) {
                if (strpos($key, 'file') !== false) {
                    if (!$hasBinary) {
                        $blob = file_get_contents($values[$no]);
                    } else {
                        $blob = $values[$no];
                    }
                    $statement->bindValue(':' . $key, $blob, PDO::PARAM_LOB);
                } else {
                    $statement->bindValue(':' . $key, $values[$no]);
                }
            }
            if ($statement->execute()) {
                $lastid = self::$dbi->lastInsertId();
                self::$dbi = null;
                return $lastid;
            } else {
                self::$dbi = null;
                return false;
            }
        } catch (PDOException $ex) {
            self::$dbi = null;
            return $this->notify('Database error: ' . $ex->getMessage(), $ex->getTrace());
        }
    }

    /**
     * @param $arrWhere
     * @return bool
     * @throws \Exception
     */
    public function Delete($arrWhere)
    {
        $del_text = "DELETE FROM $this->query WHERE ";
        foreach ($arrWhere as $col => $value) {
            $del_text .= "`" . $col . "`" . " = '" . $value . "' AND ";
        }
        $del_text = substr($del_text, 0, -4);
        try {
            $statement = self::$dbi->prepare($del_text);
            $result = $statement->execute($arrWhere);
            self::$dbi = null;
            return $result;
        } catch (PDOException $ex) {
            self::$dbi = null;
            return $this->notify('Database error: ' . $ex->getMessage(), $ex->getTrace());
        }
    }

    /**
     * @param $id
     * @param $array
     * @param bool $hasBinary
     * @return bool
     * @throws \Exception
     */
    public function Update($id, $array, $hasBinary = false)
    {
        $update_text = "UPDATE $this->query SET";
        $key_string = "";
        $key_where = " WHERE ";
        foreach ($array as $key => $val) {
            $key_string .= $key . " = :" . $key . ", ";
        }
        $key_string = substr($key_string, 0, -2);
        foreach ($id as $key => $val) {
            $key_where .= $key . " = :" . $key . " AND ";
        }
        $key_where = substr($key_where, 0, -4);
        $update_text .= " " . $key_string . $key_where;
        try {
            $statement = self::$dbi->prepare($update_text);
            foreach ($array as $key => $val) {
                if (strpos($key, 'file') !== false) {
                    if (!$hasBinary) $blob = file_get_contents($val);
                    else $blob = $val;
                    $statement->bindValue(':' . $key, $blob, PDO::PARAM_LOB);
                } else $statement->bindValue(':' . $key, $val);
            }
            foreach ($id as $key => $val) {
                if (strpos($key, 'file') !== false) {
                    if (!$hasBinary) {
                        $blob = file_get_contents($val);
                    } else {
                        $blob = $val;
                    }
                    $statement->bindValue(':' . $key, $blob, PDO::PARAM_LOB);
                } else {
                    $statement->bindValue(':' . $key, $val);
                }
            }
            $result = $statement->execute();
            self::$dbi = null;
            return $result;
        } catch (PDOException $ex) {
            self::$dbi = null;
            return $this->notify('Database error: ' . $ex->getMessage(), $ex->getTrace());
        }
    }

    /**
     * @return array|bool
     * @throws \Exception
     */
    public function GetData()
    {
        if ($this->cache) {
            $cacheConfig = Config::Data('app')['cache'];
            $memcached = new Memcached();
            $memcached->addServer($cacheConfig['host'], $cacheConfig['port']);

            $keys = hash('ripemd160', $this->query);
            $item = $memcached->get($keys);

            if ($item) {
                return $item;
            } else {
                $parameters = func_get_args();
                $argCount = count($parameters);
                $this->queryParams = $parameters;
                if ($argCount > 0) {
                    $this->query = preg_replace_callback(
                        $this->queryPattern,
                        array($this, 'queryPrepareSelect'),
                        $this->query
                    );
                }
                try {
                    $statement = self::$dbi->prepare($this->query);
                    if ($argCount > 0) {
                        $statement->execute($parameters);
                    } else {
                        $statement->execute();
                    }
                    //doing memcached storage
                    $memcached->set($keys, $statement->fetchAll(PDO::FETCH_ASSOC));
                    return $memcached->get($keys);

                } catch (PDOException $ex) {
                    self::$dbi = null;
                    return $this->notify('Database error: ' . $ex->getMessage(), $ex->getTrace());
                }
            }
        } else {
            $parameters = func_get_args();
            $argCount = count($parameters);
            $this->queryParams = $parameters;
            if ($argCount > 0) {
                $this->query = preg_replace_callback(
                    $this->queryPattern,
                    array($this, 'queryPrepareSelect'),
                    $this->query
                );
            }
            try {
                $statement = self::$dbi->prepare($this->query);
                if ($argCount > 0) {
                    $statement->execute($parameters);
                } else {
                    $statement->execute();
                }
                $result = $statement->fetchAll(PDO::FETCH_ASSOC);
                self::$dbi = null;
                return $result;
            } catch (PDOException $ex) {
                self::$dbi = null;
                return $this->notify('Database error: ' . $ex->getMessage(), $ex->getTrace());
            }
        }
        return null;
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function FirstRow()
    {
        $parameters = func_get_args();
        $argCount = count($parameters);
        $this->queryParams = $parameters;
        if ($argCount > 0) {
            $this->query = preg_replace_callback($this->queryPattern, array($this, 'queryPrepareSelect'), $this->query);
        }
        try {
            $statement = self::$dbi->prepare($this->query);
            if ($argCount > 0) {
                $statement->execute($parameters);
            } else {
                $statement->execute();
            }
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            isset($result[0]) ? $result = $result[0] : $result = null;
            self::$dbi = null;
            return $result;
        } catch (PDOException $ex) {
            self::$dbi = null;
            return $this->notify('Database error: ' . $ex->getMessage(), $ex->getTrace());
        }
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function Run()
    {
        $parameters = func_get_args();
        $argCount = count($parameters);
        $this->queryParams = $parameters;
        if ($argCount > 0) {
            $this->query = preg_replace_callback($this->queryPattern, array($this, 'queryPrepareSelect'), $this->query);
        }
        try {
            $statement = self::$dbi->prepare($this->query);
            if ($argCount > 0) {
                $result = $statement->execute($parameters);
                self::$dbi = null;
                return $result;
            } else {
                $result = $statement->execute();
                self::$dbi = null;
                return $result;
            }
        } catch (PDOException $ex) {
            self::$dbi = null;
            return $this->notify('Database error: ' . $ex->getMessage(), $ex->getTrace());
        }
    }

    /**
     * @param $name
     * @param $arrData
     * @return bool
     * @throws \Exception
     */
    public function Call($name, $arrData)
    {
        $argCount = count($arrData);
        $call = "CALL $name(";
        foreach ($arrData as $col => $value) {
            $call .= "'" . $value . "', ";
        }
        $call_text = substr($call, 0, -2);
        $call_text .= ");";
        try {
            $statement = self::$dbi->prepare($call_text);
            if ($argCount > 0) {
                $result = $statement->execute($arrData);
                self::$dbi = null;
                return $result;
            } else {
                $result = $statement->execute();
                self::$dbi = null;
                return $result;
            }
        } catch (PDOException $ex) {
            self::$dbi = null;
            return $this->notify('Database error: ' . $ex->getMessage(), $ex->getTrace());
        }
    }

    /**
     * @return string
     */
    private function queryPrepareSelect()
    {
        return '?';
    }

}