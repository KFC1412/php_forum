<?php
/**
 * JSON存储类 - 提供基于JSON文件的数据存储功能
 */

class JsonStorage {
    private static $instance = null;
    private $dataDir;
    private $prefix;
    private $cache = [];
    private $lastInsertId;
    
    private function __construct() {
        $config_file = __DIR__ . '/../config/config.php';
        
        if (file_exists($config_file)) {
            require_once $config_file;
        }
        
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        if (defined('JSON_STORAGE_DIR')) {
            $this->dataDir = __DIR__ . '/../' . JSON_STORAGE_DIR . '/';
        } else {
            $this->dataDir = __DIR__ . '/../storage/json/';
        }
        
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function getTableFile($table) {
        $tableName = strpos($table, $this->prefix) === 0 ? $table : $this->prefix . $table;
        return $this->dataDir . $tableName . '.json';
    }
    
    public function loadTable($table) {
        if (isset($this->cache[$table])) {
            return $this->cache[$table];
        }
        
        $file = $this->getTableFile($table);
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if ($data === null) {
                $data = ['data' => [], 'auto_increment' => 1];
            }
        } else {
            $data = ['data' => [], 'auto_increment' => 1];
        }
        
        // 对于messages表，确保sender_id和receiver_id都作为字符串处理
        if (strpos($table, 'messages') !== false && isset($data['data'])) {
            foreach ($data['data'] as &$row) {
                if (isset($row['sender_id'])) {
                    $row['sender_id'] = (string)$row['sender_id'];
                }
                if (isset($row['receiver_id'])) {
                    $row['receiver_id'] = (string)$row['receiver_id'];
                }
            }
        }
        
        $this->cache[$table] = $data;
        return $data;
    }
    
    private function saveTable($table, $data) {
        $file = $this->getTableFile($table);
        $this->cache[$table] = $data;
        
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return file_put_contents($file, $content) !== false;
    }
    
    private function getTableName($table) {
        return strpos($table, $this->prefix) === 0 ? $table : $this->prefix . $table;
    }
    
    public function prepare($sql) {
        return new JsonQuery($this, $sql);
    }
    
    public function execute($sql, $params = []) {
        return $this->query($sql, $params);
    }
    
    public function query($sql, $params = []) {
        return new JsonQuery($this, $sql, $params);
    }
    
    public function fetch($sql, $params = []) {
        $query = new JsonQuery($this, $sql, $params);
        return $query->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $query = new JsonQuery($this, $sql, $params);
        return $query->fetchAll();
    }
    
    public function fetchColumn($sql, $params = []) {
        $query = new JsonQuery($this, $sql, $params);
        return $query->fetchColumn();
    }
    
    public function insert($table, $data) {
        $table = $this->getTableName($table);
        $tableData = $this->loadTable($table);
        
        if (!isset($data['id'])) {
            $data['id'] = $tableData['auto_increment'];
            $tableData['auto_increment']++;
        }
        
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        // 对于messages表，确保sender_id和receiver_id都作为字符串存储
        if (strpos($table, 'messages') !== false) {
            if (isset($data['sender_id'])) {
                $data['sender_id'] = (string)$data['sender_id'];
            }
            if (isset($data['receiver_id'])) {
                $data['receiver_id'] = (string)$data['receiver_id'];
            }
        }
        
        $tableData['data'][] = $data;
        
        $this->lastInsertId = $data['id'];
        
        return $this->saveTable($table, $tableData) ? $data['id'] : false;
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $table = $this->getTableName($table);
        $tableData = $this->loadTable($table);
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $updated = 0;
        foreach ($tableData['data'] as &$row) {
            if ($this->matchWhere($row, $where, $whereParams)) {
                foreach ($data as $key => $value) {
                    $row[$key] = $value;
                }
                $updated++;
            }
        }
        
        $this->saveTable($table, $tableData);
        return $updated > 0;
    }
    
    public function delete($table, $where, $whereParams = []) {
        $table = $this->getTableName($table);
        $tableData = $this->loadTable($table);
        
        $newData = [];
        foreach ($tableData['data'] as $row) {
            if (!$this->matchWhere($row, $where, $whereParams)) {
                $newData[] = $row;
            }
        }
        
        $tableData['data'] = $newData;
        return $this->saveTable($table, $tableData);
    }
    
    private function matchWhere($row, $where, $params) {
        if (preg_match('/`?(\w+)`?\s*=\s*:?(\w*)/', $where, $matches)) {
            $field = $matches[1];
            $paramKey = $matches[2] ?: 0;
            
            $value = isset($params[$paramKey]) ? $params[$paramKey] : (isset($params[0]) ? $params[0] : null);
            
            return isset($row[$field]) && $row[$field] == $value;
        }
        
        if (preg_match('/`?(\w+)`?\s*=\s*\?/', $where, $matches)) {
            $field = $matches[1];
            $value = isset($params[0]) ? $params[0] : null;
            return isset($row[$field]) && $row[$field] == $value;
        }
        
        return false;
    }
    
    public function select($table, $conditions = [], $orderBy = null, $limit = null, $offset = 0) {
        $table = $this->getTableName($table);
        $tableData = $this->loadTable($table);
        
        $result = $tableData['data'];
        
        foreach ($conditions as $field => $value) {
            $result = array_filter($result, function($row) use ($field, $value) {
                return isset($row[$field]) && $row[$field] == $value;
            });
        }
        
        if ($orderBy) {
            // 处理多个排序字段
            $orderFields = explode(',', $orderBy);
            
            usort($result, function($a, $b) use ($orderFields) {
                foreach ($orderFields as $orderField) {
                    $orderField = trim($orderField);
                    $parts = explode(' ', $orderField);
                    $field = $parts[0];
                    $dir = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
                    
                    $valA = isset($a[$field]) ? $a[$field] : '';
                    $valB = isset($b[$field]) ? $b[$field] : '';
                    
                    if ($valA !== $valB) {
                        if ($dir === 'DESC') {
                            return $valB <=> $valA;
                        }
                        return $valA <=> $valB;
                    }
                }
                return 0;
            });
        }
        
        if ($offset > 0) {
            $result = array_slice($result, $offset);
        }
        
        if ($limit) {
            $result = array_slice($result, 0, $limit);
        }
        
        return array_values($result);
    }
    
    public function selectAll($table, $conditions = [], $orderBy = null, $limit = null, $offset = 0) {
        return $this->select($table, $conditions, $orderBy, $limit, $offset);
    }
    
    public function findById($table, $id) {
        $rows = $this->select($table, ['id' => $id]);
        return $rows ? $rows[0] : null;
    }
    
    public function lastInsertId() {
        return $this->lastInsertId;
    }
    
    public function createDatabase($dbname) {
        return true;
    }
    
    public function useDatabase($dbname) {
        return true;
    }
    
    public function createTable($table, $schema) {
        $table = $this->getTableName($table);
        $file = $this->getTableFile($table);
        
        if (!file_exists($file)) {
            $data = ['data' => [], 'auto_increment' => 1, 'schema' => $schema];
            return $this->saveTable($table, $data);
        }
        
        return true;
    }
    
    public function getTables() {
        $tables = [];
        $files = glob($this->dataDir . $this->prefix . '*.json');
        
        foreach ($files as $file) {
            $tableName = basename($file, '.json');
            $tables[] = $tableName;
        }
        
        return $tables;
    }
    
    public function count($table, $conditions = []) {
        $result = $this->select($table, $conditions);
        return count($result);
    }
    
    public function setPrefix($prefix) {
        $this->prefix = $prefix;
    }
    
    public function setDataDir($dir) {
        $this->dataDir = rtrim($dir, '/\\') . '/';
        $this->cache = [];
        
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    public function getDataDir() {
        return $this->dataDir;
    }
}

class JsonQuery {
    private $storage;
    private $sql;
    private $params;
    private $result;
    
    public function __construct($storage, $sql, $params = []) {
        $this->storage = $storage;
        $this->sql = $sql;
        $this->params = $params;
        $this->result = $this->executeQuery();
    }
    
    private function executeQuery() {
        $sql = trim($this->sql);
        $sqlUpper = strtoupper($sql);
        
        if (strpos($sqlUpper, 'SELECT') === 0) {
            return $this->executeSelect();
        } elseif (strpos($sqlUpper, 'INSERT') === 0) {
            return $this->executeInsert();
        } elseif (strpos($sqlUpper, 'UPDATE') === 0) {
            return $this->executeUpdate();
        } elseif (strpos($sqlUpper, 'DELETE') === 0) {
            return $this->executeDelete();
        }
        
        return [];
    }
    
    private function parseTable($sql) {
        if (preg_match('/FROM\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }
        if (preg_match('/INTO\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }
        if (preg_match('/UPDATE\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function parseWhere($sql) {
        if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER|\s+LIMIT|$)/i', $sql, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    
    private function parseOrderBy($sql) {
        if (preg_match('/ORDER\s+BY\s+(.+?)(?:\s+LIMIT|$)/i', $sql, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    
    private function parseLimit($sql) {
        if (preg_match('/LIMIT\s+(\d+)\s*,\s*(\d+)/i', $sql, $matches)) {
            return [(int)$matches[1], (int)$matches[2]]; // [offset, limit]
        } elseif (preg_match('/LIMIT\s+(\d+)/i', $sql, $matches)) {
            return [0, (int)$matches[1]]; // [0, limit]
        }
        return null;
    }
    
    private function isCountQuery($sql) {
        return preg_match('/SELECT\s+COUNT\s*\(\s*\*\s*\)/i', $sql);
    }
    
    private function executeSelect() {
        $table = $this->parseTable($this->sql);
        if (!$table) return [];
        
        $tableData = $this->storage->loadTable($table);
        $result = $tableData['data'];
        
        $where = $this->parseWhere($this->sql);
        if ($where) {
            $result = $this->applyWhere($result, $where);
        }
        
        if ($this->isCountQuery($this->sql)) {
            return [['count' => count($result)]];
        }
        
        $orderBy = $this->parseOrderBy($this->sql);
        if ($orderBy) {
            $result = $this->applyOrderBy($result, $orderBy);
        }
        
        $limit = $this->parseLimit($this->sql);
        if ($limit) {
            if (is_array($limit)) {
                $offset = $limit[0];
                $limitValue = $limit[1];
                if ($offset > 0) {
                    $result = array_slice($result, $offset);
                }
                $result = array_slice($result, 0, $limitValue);
            } else {
                $result = array_slice($result, 0, $limit);
            }
        }
        
        return $result;
    }
    
    private function applyWhere($data, $where) {
        $result = [];
        
        foreach ($data as $row) {
            if ($this->matchRowWhere($row, $where)) {
                $result[] = $row;
            }
        }
        
        return $result;
    }
    
    private function matchRowWhere($row, $where) {
        // 处理OR逻辑
        $orConditions = $this->splitConditions($where, 'OR');
        
        foreach ($orConditions as $orCondition) {
            $andConditions = $this->splitConditions($orCondition, 'AND');
            $andMatch = true;
            
            foreach ($andConditions as $condition) {
                $condition = trim($condition);
                // 移除括号
                while (strpos($condition, '(') === 0 && strrpos($condition, ')') === strlen($condition) - 1) {
                    $condition = trim(substr($condition, 1, -1));
                }
                $condition = trim($condition);
                
                if (preg_match('/`?(\w+)`?\s*!=\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    // 计算参数索引
                    $paramIndex = 0;
                    for ($i = 0; $i < strlen($where); $i++) {
                        if ($where[$i] == '?') {
                            if (strpos(substr($where, 0, $i), $condition) !== false) {
                                break;
                            }
                            $paramIndex++;
                        }
                    }
                    $value = isset($this->params[$paramIndex]) ? $this->params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] == (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*!=\s*:(\w+)/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramName = $matches[2];
                    $value = isset($this->params[$paramName]) ? $this->params[$paramName] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] == (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*<>\s*:(\w+)/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramName = $matches[2];
                    $value = isset($this->params[$paramName]) ? $this->params[$paramName] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] == (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*=\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    // 计算参数索引
                    $paramIndex = 0;
                    for ($i = 0; $i < strlen($where); $i++) {
                        if ($where[$i] == '?') {
                            if (strpos(substr($where, 0, $i), $condition) !== false) {
                                break;
                            }
                            $paramIndex++;
                        }
                    }
                    $value = isset($this->params[$paramIndex]) ? $this->params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] != (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*=\s*:(\w+)/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramName = $matches[2];
                    $value = isset($this->params[$paramName]) ? $this->params[$paramName] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] != (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*=\s*\'?([^\']+)\'?/', $condition, $matches)) {
                    $field = $matches[1];
                    $value = $matches[2];
                    
                    if (!isset($row[$field]) || (string)$row[$field] != (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*!=\s*\'?([^\']+)\'?/', $condition, $matches)) {
                    $field = $matches[1];
                    $value = $matches[2];
                    
                    if (!isset($row[$field]) || (string)$row[$field] == (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s+IN\s*\(([^\)]+)\)/i', $condition, $matches)) {
                    $field = $matches[1];
                    $inValuesStr = $matches[2];
                    
                    // 检查是否是参数绑定的IN条件 (IN (?, ?, ?))
                    if (preg_match_all('/\?/', $inValuesStr, $placeholders)) {
                        $paramCount = count($placeholders[0]);
                        $inValues = [];
                        
                        // 计算起始参数索引
                        $startIndex = 0;
                        for ($i = 0; $i < strlen($where); $i++) {
                            if ($where[$i] == '?') {
                                if (strpos(substr($where, 0, $i), $condition) !== false) {
                                    break;
                                }
                                $startIndex++;
                            }
                        }
                        
                        // 收集参数值
                        for ($i = 0; $i < $paramCount; $i++) {
                            if (isset($this->params[$startIndex + $i])) {
                                $inValues[] = (string)$this->params[$startIndex + $i];
                            }
                        }
                    } else {
                        // 直接在SQL中写值的情况
                        $inValues = preg_replace('/[\'"\s]/', '', $inValuesStr);
                        $inValues = explode(',', $inValues);
                    }
                    
                    if (!isset($row[$field]) || !in_array((string)$row[$field], $inValues)) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s+LIKE\s*\?/i', $condition, $matches)) {
                    $field = $matches[1];
                    // 计算参数索引
                    $paramIndex = 0;
                    for ($i = 0; $i < strlen($where); $i++) {
                        if ($where[$i] == '?') {
                            if (strpos(substr($where, 0, $i), $condition) !== false) {
                                break;
                            }
                            $paramIndex++;
                        }
                    }
                    $pattern = isset($this->params[$paramIndex]) ? $this->params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || !$this->likeMatch((string)$row[$field], (string)$pattern)) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s+LIKE\s*:([\w_]+)/i', $condition, $matches)) {
                    $field = $matches[1];
                    $paramName = $matches[2];
                    $pattern = isset($this->params[$paramName]) ? $this->params[$paramName] : null;
                    
                    if (!isset($row[$field]) || !$this->likeMatch((string)$row[$field], (string)$pattern)) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s+IS\s+NOT\s+NULL/i', $condition, $matches)) {
                    $field = $matches[1];
                    if (!isset($row[$field]) || $row[$field] === null) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s+IS\s+NULL/i', $condition, $matches)) {
                    $field = $matches[1];
                    if (isset($row[$field]) && $row[$field] !== null) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*!=\s*\'\'/i', $condition, $matches)) {
                    $field = $matches[1];
                    if (!isset($row[$field]) || (string)$row[$field] == '') {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*=\s*\'\'/i', $condition, $matches)) {
                    $field = $matches[1];
                    if (!isset($row[$field]) || (string)$row[$field] != '') {
                        $andMatch = false;
                        break;
                    }
                } else {
                    // 条件不匹配任何模式，设置为不匹配
                    $andMatch = false;
                    break;
                }
            }
            
            if ($andMatch) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 分割条件，考虑括号
     */
    private function splitConditions($condition, $delimiter) {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($condition);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $condition[$i];
            
            if ($char == '(') {
                $depth++;
            } elseif ($char == ')') {
                $depth--;
            }
            
            // 当遇到分隔符且不在括号内时
            if (strtoupper(substr($condition, $i, strlen($delimiter))) == $delimiter && $depth == 0) {
                $parts[] = trim($current);
                $current = '';
                $i += strlen($delimiter) - 1;
            } else {
                $current .= $char;
            }
        }
        
        if (trim($current)) {
            $parts[] = trim($current);
        }
        
        return $parts;
    }
    
    private function applyOrderBy($data, $orderBy) {
        // 处理多个排序字段
        $orderFields = explode(',', $orderBy);
        
        usort($data, function($a, $b) use ($orderFields) {
            foreach ($orderFields as $orderField) {
                $orderField = trim($orderField);
                $parts = explode(' ', $orderField);
                $field = $parts[0];
                // 移除字段名周围的反引号
                $field = str_replace('`', '', $field);
                $dir = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
                
                $valA = isset($a[$field]) ? $a[$field] : '';
                $valB = isset($b[$field]) ? $b[$field] : '';
                
                if ($valA !== $valB) {
                    if ($dir === 'DESC') {
                        return $valB <=> $valA;
                    }
                    return $valA <=> $valB;
                }
            }
            return 0;
        });
        
        return $data;
    }
    
    /**
     * 处理LIKE匹配
     */
    private function likeMatch($value, $pattern) {
        // 替换SQL LIKE模式为正则表达式
        $pattern = str_replace('%', '.*', $pattern);
        $pattern = str_replace('_', '.', $pattern);
        $pattern = '/^' . $pattern . '$/i';
        
        return preg_match($pattern, $value) === 1;
    }
    
    private function executeInsert() {
        if (preg_match('/INSERT\s+INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)/i', $this->sql, $matches)) {
            $table = $matches[1];
            $fields = array_map('trim', explode(',', str_replace('`', '', $matches[2])));
            $placeholders = array_map('trim', explode(',', $matches[3]));
            
            $data = [];
            foreach ($fields as $index => $field) {
                $placeholder = $placeholders[$index];
                if (strpos($placeholder, '?') !== false) {
                    $data[$field] = isset($this->params[$index]) ? $this->params[$index] : null;
                } elseif (strpos($placeholder, ':') === 0) {
                    $paramName = substr($placeholder, 1);
                    $data[$field] = isset($this->params[$paramName]) ? $this->params[$paramName] : null;
                } else {
                    $data[$field] = $placeholder;
                }
            }
            
            return $this->storage->insert($table, $data);
        }
        
        return false;
    }
    
    private function executeUpdate() {
        if (preg_match('/UPDATE\s+`?(\w+)`?\s+SET\s+(.+?)\s+WHERE\s+(.+)/i', $this->sql, $matches)) {
            $table = $matches[1];
            $setClause = $matches[2];
            $whereClause = $matches[3];
            
            $data = [];
            if (preg_match_all('/`?(\w+)`?\s*=\s*\?/', $setClause, $setMatches, PREG_SET_ORDER)) {
                $paramIndex = 0;
                foreach ($setMatches as $match) {
                    $data[$match[1]] = isset($this->params[$paramIndex]) ? $this->params[$paramIndex] : null;
                    $paramIndex++;
                }
            }
            
            return $this->storage->update($table, $data, $whereClause, $this->params);
        }
        
        return false;
    }
    
    private function executeDelete() {
        if (preg_match('/DELETE\s+FROM\s+`?(\w+)`?\s+WHERE\s+(.+)/i', $this->sql, $matches)) {
            $table = $matches[1];
            $whereClause = $matches[2];
            
            return $this->storage->delete($table, $whereClause, $this->params);
        }
        
        return false;
    }
    
    public function fetch() {
        if (is_array($this->result) && !empty($this->result)) {
            return $this->result[0];
        }
        return false;
    }
    
    public function fetchAll() {
        if (is_array($this->result)) {
            return $this->result;
        }
        return [];
    }
    
    public function fetchColumn() {
        if (is_array($this->result) && !empty($this->result)) {
            $row = $this->result[0];
            if ($this->isCountQuery($this->sql) && isset($row['count'])) {
                return $row['count'];
            }
            return reset($row);
        }
        return 0;
    }
    
    public function execute() {
        return $this->result !== false;
    }
}
?>
