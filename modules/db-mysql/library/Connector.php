<?php
/**
 * Database connector provider
 * @package db-mysql
 * @version 0.0.1
 * @upgrade true
 */

namespace DbMysql\Library;

class Connector{
    
    private static $conns = [];
    
    static function getConnection($name){
        if(isset(self::$conns[$name]))
            return self::$conns[$name];
        
        $args = [
            'host'      => 'mysqli.default_host',
            'user'      => 'mysqli.default_user',
            'passwd'    => 'mysqli.default_pw',
            'dbname'    => '',
            'port'      => 'mysqli.default_port',
            'socket'    => 'mysqli.default_socket'
        ];
        
        $fn_args = [];
        
        $db = \Phun::$config['db'][$name];
        
        foreach($args as $arg => $def)
            $fn_args[] = $db[$arg] ?? ini_get($def);
        
        $conn = call_user_func_array('mysqli_connect', $fn_args);
        
        if(mysqli_connect_error())
            throw new \Exception('Database connection `' . $name . '` fail');
        
        mysqli_set_charset($conn, 'utf8');
        
        self::$conns[$name] = $conn;
        return self::$conns[$name];
    }
}