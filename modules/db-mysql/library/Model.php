<?php
/**
 * Model extender
 * @package db-mysql
 * @version 0.0.1
 * @upgrade true
 */

use DbMysql\Library\Connector;

class Model
{

    private static $models = [];
    private static $db_target;
    private static $last_conn;
    
    public function __construct(){
        if(!self::$db_target){
            $db_target = Phun::$config['db_target'] ?? ['read'=>'default', 'write'=>'default'];
            self::$db_target = (object)[
                'read' => $db_target['read'] ?? 'default',
                'write'=> $db_target['write']?? 'default'
            ];
        }
        
        $model = (object)[
            'table' => '',
            'conns' => clone self::$db_target
        ];
        
        $cls = get_class($this);
        
        if(isset($this->table))
            $model->table = $this->table;
        else{
            $parts = explode('\\', $cls);
            $table = end($parts);
            $table = preg_replace('!([a-z])([A-Z])!', '$1_$2', $table);
            $model->table = strtolower($table);
        }
        
        $config = Phun::$config['db_model'] ?? [];
        if(isset($config[$cls])){
            if(is_string($config[$cls])){
                $model->conns->read = $model->conns->write = $config[$cls];
            }elseif(is_array($config[$cls])){
                $model->conns->read = $config[$cls]['read'] ?? 'default';
                $model->conns->write= $config[$cls]['write']?? 'default';
            }
        }
        
        $this->model = $model;
    }
    
    private function avg($field, $where=null){
        $sql = $this->putField('SELECT AVG(:field) AS `avg` FROM :table', [
            'field' => $field,
            'table' => $this->getTable()
        ]);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        $result = $this->query($sql, 'read');
        if(!$result)
            return 0;
        return $result[0]->avg ?? 0;
    }
    
    private function count($where=null){
        $sql = $this->putField('SELECT COUNT(*) AS `count` FROM :table', [
            'table' => $this->getTable()
        ]);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        $result = $this->query($sql, 'read');
        if(!$result)
            return 0;
        return $result[0]->count ?? 0;
    }
    
    private function countGroup($group, $where=null){
        $sql = 'SELECT COUNT(*) AS `count`, :field AS `key` FROM :table';
        if($where)
            $sql.= ' WHERE :where';
        $sql.= ' GROUP BY `key`';
        if($where)
            $sql = $this->putWhere($sql, $where);
        $sql = $this->putField($sql, ['field' => $group, 'table' => $this->getTable()]);
        
        $result = $this->query($sql, 'read');
        if(!$result)
            return false;
        
        return array_column($result, 'count', 'key');
    }
    
    private function create($row){
        if(!$row)
            return false;
        $row = (array)$row;
        
        $fields = array_keys($row);
        
        $sql = $this->putField('INSERT INTO :table (:fields) VALUES :values', [
            'table'  => $this->getTable(),
            'fields' => $fields
        ]);
        
        $sql = $this->putValue($sql, ['values' => $row]);
        
        $result = $this->query($sql, 'write');
        if(!$result)
            return false;
        return $this->lastId();
    }
    
    private function createMany($rows){
        if(!$rows)
            return false;
        
        $fields = [];
        foreach($rows as $row){
            foreach($row as $field => $val){
                if(!in_array($field, $fields))
                    $fields[] = $field;
            }
        }
        
        $sql = $this->putField('INSERT INTO :table (:fields) VALUES ', [
            'table' => $this->getTable(),
            'fields'=> $fields
        ]);
        
        $range = range(1, count($rows));
        array_walk($range, function(&$a){ $a = 'field'.$a; });
        $sql.= ':' . implode(', :', $range);
        $sql.= ';';
        
        $used_rows = [];
        foreach($rows as $row){
            $used_row = [];
            foreach($fields as $field)
                $used_row[$field] = $row[$field] ?? null;
            $used_rows[] = $used_row;
        }
        
        $used_rows = array_combine($range, $used_rows);
        $sql = $this->putValue($sql, $used_rows);
        
        return $this->query($sql, 'write');
    }
    
    private function dec($field, $where=null, $total=1){
        $sql = $this->putField('UPDATE :table SET :field = :field - :total', [
            'table' => $this->getTable(),
            'field' => $field
        ]);
        $sql = $this->putValue($sql, ['total' => $total]);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        return $this->query($sql, 'write');
    }
    
    private function escape(String $str){
        return mysqli_real_escape_string($this->getConn(), $str);
    }
    
    private function get($where=null, $total=true, $page=false, $order=''){
        $sql = $this->putField('SELECT * FROM :table', ['table'=>$this->getTable()]);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        if($order)
            $sql.= ' ORDER BY ' . $order;
        
        if($total === false){
            $sql.= ' LIMIT 1';
        }elseif(is_numeric($total)){
            $sql.= ' LIMIT :limit';
            $offset = 0;
            
            if(is_numeric($page)){
                $page--;
                $offset = $page * $total;
                $sql.= ' OFFSET :offset';
            }
            
            $sql = $this->putValue($sql, [
                'limit' => $total,
                'offset'=> $offset
            ]);
        }
        
        $result = $this->query($sql, 'read');
        if(!$result)
            return false;
        
        if($total === false)
            return $result[0];
        return $result;
    }
    
    private function getConn($target='read'){
        $name = $this->model->conns->$target;
        return Connector::getConnection($name);
    }
    
    private function getConnName($target='read'){
        return $this->model->conns->$target;
    }
    
    private function getQField(){
        return $this->q_field ?? null;
    }
    
    private function getTable(){
        return $this->model->table;
    }
    
    private function inc($field, $where=null, $total=1){
        $sql = $this->putField('UPDATE :table SET :field = :field + :total', [
            'table' => $this->getTable(),
            'field' => $field
        ]);
        $sql = $this->putValue($sql, ['total' => $total]);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        return $this->query($sql, 'write');
    }
    
    private function lastError(){
        if(!$this->last_conn)
            return;
        
        return $this->getConn( $this->last_conn )->error;
    }
    
    private function lastId($target='write'){
        return mysqli_insert_id($this->getConn($target));
    }
    
    private function lastQuery(){
        return $this->last_query;
    }
    
    private function max($field, $where=null){
        $sql = $this->putField('SELECT MAX(:field) AS `max` FROM :table', [
            'field' => $field,
            'table' => $this->getTable()
        ]);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        $result = $this->query($sql, 'read');
        if(!$result)
            return 0;
        return $result[0]->max ?? 0;
    }
    
    private function min($field, $where=null){
        $sql = $this->putField('SELECT MIN(:field) AS `min` FROM :table', [
            'field' => $field,
            'table' => $this->getTable()
        ]);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        $result = $this->query($sql, 'read');
        if(!$result)
            return 0;
        return $result[0]->min ?? 0;
    }
    
    private function putField($sql, $args){
        uksort($args, function($a,$b){
            return strcasecmp($b, $a);
        });
        
        foreach($args as $arg => $val){
            if(is_string($val)){
                $val = "`$val`";
                if(strstr($val, '.'))
                    $val = str_replace('.', "`.`", $val);
            }elseif(is_array($val)){
                $used_val = [];
                foreach($val as $va){
                    $va = "`$va`";
                    if(strstr($va, '.'))
                        $va = str_replace('.', "`.`", $va);
                    $used_val[] = $va;
                }
                $val = implode(', ', $used_val);
            }
            $sql = str_replace(':' . $arg, $val, $sql);
        }
        
        return $sql;
    }
    
    private function putValue($sql, $args){
        uksort($args, function($a,$b){
            return strcasecmp($b, $a);
        });
        
        foreach($args as $arg => $val){
            if(is_string($val))
                $val = "'" . $this->escape($val) . "'";
            elseif(is_bool($val))
                $val = $val ? 'TRUE' : 'FALSE';
            elseif(is_null($val))
                $val = 'NULL';
            elseif(is_array($val)){
                $used_val = [];
                foreach($val as $va){
                    if(is_string($va))
                        $va = "'" . $this->escape($va) . "'";
                    elseif(is_bool($va))
                        $va = $va ? 'TRUE' : 'FALSE';
                    elseif(is_null($va))
                        $va = 'NULL';
                    $used_val[] = $va;
                }
                $val = '(' . implode(', ', $used_val) . ')';
            }
            $sql = str_replace(':'.$arg, $val, $sql);
        }
        
        return $sql;
    }
    
    private function putWhere($sql, $where){
        if(!$where)
            return $sql;
        
        $used_where = ['','bind' => []];
        
        if(is_numeric($where)){
            $used_where[0] = '`id` = :id';
            $used_where['bind'] = ['id' => $where];
        }elseif(is_string($where)){
            $used_where[0] = $where;
        }elseif(is_array($where)){
            // indexed array 
            if(is_indexed_array($where)){
                // there's only 1 item and it's string
                if(count($where) === 1 && is_string($where[0])){
                    $used_where[0] = $where[0];
                }else{
                    $used_where[0] = '`id` IN :ids';
                    $used_where['bind'] = ['ids' => $where];
                }
            }else{
                // index 0 and bind exists
                if(isset($where[0]) && isset($where['bind'])){
                    $used_where = $where;
                }else{
                    $cond = [];
                    $bind = [];
                    foreach($where as $key => $val){
                        $q_used = false;
                        
                        if($key == 'q' && !is_array($val) && isset($this->q_field)){
                            $val = $this->escape($val);
                            $scond = '`' . $this->q_field . '` LIKE \'%' . $val . '%\'';
                            $q_used = true;
                        }
                        
                        if(!$q_used){
                            $scond = '`' . str_replace('.', '`.`', $key) . '`';
                            if(is_array($val)){
                                if($val[0] === '__op'){
                                    $scond.= ' ' . $val[1] . ' ';
                                    $bind[$key] = $val[2];
                                    $scond.= ':' . $key;
                                }elseif($val[0] === '__between'){
                                    $scond.= ' BETWEEN :' . $key . '___min AND :' . $key . '___max';
                                    $bind[$key.'___min'] = $val[1];
                                    $bind[$key.'___max'] = $val[2];
                                }else{
                                    $scond.= ' IN ';
                                    $bind[$key] = $val;
                                    $scond.= ':' . $key;
                                }
                            }else{
                                if(is_null($val)){
                                    $scond.= ' IS :' . $key;
                                    $bind[$key] = $val;
                                }else{
                                    $scond.= ' = :' . $key;
                                    $bind[$key] = $val;
                                }
                            }
                        }
                        
                        $cond[] = $scond;
                    }
                    
                    $used_where[0] = implode(' AND ', $cond);
                    $used_where['bind'] = $bind;
                }
            }
        }
        
        $sql = str_replace(':where', $used_where[0], $sql);
        return $this->putValue($sql, $used_where['bind']);
    }
    
    private function query($sql, $target='read'){
        $this->last_query = $sql;
        $this->last_conn  = $target;
        $conn = $this->getConn($target);
        $result = mysqli_query($conn, $sql);
        if(is_bool($result))
            return $result;
        
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        mysqli_free_result($result);
        
        array_walk($rows, function(&$a){ $a = (object)$a; });
        
        return $rows;
    }
    
    private function remove($where=null){
        $sql = $this->putField('DELETE FROM :table', ['table' => $this->getTable()]);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        return $this->query($sql, 'write');
    }
    
    private function set($row, $where=null){
        if(!$row)
            return false;
        $row = (array)$row;
        
        $sql = 'UPDATE :table SET ';
        
        $bind_field = [
            'table' => $this->getTable()
        ];
        $bind_value = [];
        $bind_sql   = [];
        foreach($row as $field => $value){
            $bind_sql[] = ':' . $field . ' = :val_' . $field;
            $bind_field[$field] = $field;
            $bind_value['val_' . $field] = $value;
        }
        
        $sql.= implode(', ', $bind_sql);
        
        $sql = $this->putField($sql, $bind_field);
        $sql = $this->putValue($sql, $bind_value);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        return $this->query($sql, 'write');
    }
    
    private function sum($field, $where=null){
        $sql = $this->putField('SELECT SUM(:field) AS `sum` FROM :table', [
            'field' => $field,
            'table' => $this->getTable()
        ]);
        
        if($where){
            $sql.= ' WHERE :where';
            $sql = $this->putWhere($sql, $where);
        }
        
        $result = $this->query($sql, 'read');
        if(!$result)
            return 0;
        return $result[0]->sum ?? 0;
    }
    
    private function truncate($target='write'){
        $sql = $this->putField('TRUNCATE :table;', ['table'=>$this->getTable()]);
        return $this->query($sql, $target);
    }
    
    ////////////////////////////////////////////////////////////////////////////
    // STATIC AREA
    ////////////////////////////////////////////////////////////////////////////
    
    public static function __init(){
        $cls = get_called_class();
        if($cls === 'Model')
            return;
        
        self::$models[$cls] = new $cls();
    }
    
    public static function __callStatic($name, $args){
        $cls = get_called_class();
        $model = self::$models[$cls];
        return call_user_func_array([$model, $name], $args);
    }
}