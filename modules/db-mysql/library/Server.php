<?php
/**
 * Server tester
 * @package db-mysql
 * @version 0.0.1
 * @upgrade true
 */

namespace DbMysql\Library;

class Server{
    
    static function software(){
        $result = [
            'success' => false,
            'info'    => false
        ];
        
        $config = &\Phun::$dispatcher->config;
        $conns  = $config->db;
        
        $last_ver = null;
        
        foreach($conns as $name => $conf){
            try{
                $conn = Connector::getConnection($name);
            }catch(\Exception $e){
                $result['info'] = $e->getMessage();
                break;
            }
            
            $last_ver = $ver = mysqli_get_server_info($conn);
            
            if(!version_compare('5.6.23', $ver, '=')){
                $result['info'] = $ver;
                break;
            }
        }
        
        if(!$result['info']){
            $result['success'] = true;
            $result['info'] = $last_ver;
        }
        
        return $result;
    }
}