<?php

namespace App\Storage;

class JsonStorage implements StorageInterface {
    private static $instance = null;
    private $storageDir;
    private $lastInsertId;
    
    private function __construct() {
        $this->storageDir = __DIR__ . '/../../storage/json';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function prepare($sql) {
        return $sql;
    }
    
    public function execute($sql, $params = []) {
        // JSON存储不需要执行SQL，这里只是模拟
        return true;
    }
    
    public function query($sql, $params = []) {
        return new JsonQuery($sql, $params, $this);
    }
    
    public function fetch($sql, $params = []) {
        $query = $this->query($sql, $params);
        return $query->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $query = $this->query($sql, $params);
        return $query->fetchAll();
    }
    
    public function fetchColumn($sql, $params = []) {
        $query = $this->query($sql, $params);
        $result = $query->fetchColumn();
        return $result;
    }
    
    public function insert($table, $data) {
        $tableFile = $this->getTableFile($table);
        $dataList = $this->loadTable($table);
        
        // 生成ID
        $id = $this->generateId($table);
        $data['id'] = $id;
        
        // 添加时间戳
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $dataList[] = $data;
        $this->saveTable($table, $dataList);
        $this->lastInsertId = $id;
        return true;
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $dataList = $this->loadTable($table);
        $updated = false;
        
        foreach ($dataList as &$row) {
            if ($this->matchRowWhere($row, $where, $whereParams)) {
                $row = array_merge($row, $data);
                if (!isset($row['updated_at'])) {
                    $row['updated_at'] = date('Y-m-d H:i:s');
                }
                $updated = true;
            }
        }
        
        if ($updated) {
            $this->saveTable($table, $dataList);
        }
        return $updated;
    }
    
    public function delete($table, $where, $whereParams = []) {
        $dataList = $this->loadTable($table);
        $newDataList = [];
        
        foreach ($dataList as $row) {
            if (!$this->matchRowWhere($row, $where, $whereParams)) {
                $newDataList[] = $row;
            }
        }
        
        if (count($newDataList) !== count($dataList)) {
            $this->saveTable($table, $newDataList);
            return true;
        }
        return false;
    }
    
    public function lastInsertId() {
        return $this->lastInsertId;
    }
    
    public function createDatabase($dbname) {
        // JSON存储不需要创建数据库
        return true;
    }
    
    public function useDatabase($dbname) {
        // JSON存储不需要切换数据库
        return true;
    }
    
    public function getTableFile($table) {
        return $this->storageDir . '/' . $table . '.json';
    }
    
    private function loadTable($table) {
        $file = $this->getTableFile($table);
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true) ?: [];
            // 返回data字段的内容，如果不存在则返回空数组
            return isset($data['data']) ? $data['data'] : [];
        }
        return [];
    }
    
    private function saveTable($table, $data) {
        $file = $this->getTableFile($table);
        // 检查文件是否存在，如果存在则保留auto_increment和schema字段
        $existingData = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $existingData = json_decode($content, true) ?: [];
        }
        // 构建新的数据结构
        $newData = [
            'data' => $data,
            'auto_increment' => $existingData['auto_increment'] ?? 1,
            'schema' => $existingData['schema'] ?? ''
        ];
        file_put_contents($file, json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    
    private function generateId($table) {
        $dataList = $this->loadTable($table);
        $maxId = 0;
        foreach ($dataList as $row) {
            if (isset($row['id']) && $row['id'] > $maxId) {
                $maxId = $row['id'];
            }
        }
        return $maxId + 1;
    }
    
    private function matchRowWhere($row, $where, $params = []) {
        // 处理OR逻辑
        $orConditions = $this->splitConditions($where, 'OR');
        
        foreach ($orConditions as $orCondition) {
            $andConditions = $this->splitConditions($orCondition, 'AND');
            $andMatch = true;
            
            foreach ($andConditions as $condition) {
                $condition = trim($condition);
                
                if (preg_match('/`?(\w+)`?\s*=\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] != (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*=\s*:([\w_]+)/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramName = $matches[2];
                    $value = isset($params[$paramName]) ? $params[$paramName] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] != (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*LIKE\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || !fnmatch(str_replace('%', '*', $value), $row[$field])) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*!=\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] == (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*>\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || $row[$field] <= $value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*<\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || $row[$field] >= $value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*>={0,1}\s*\d+/', $condition, $matches)) {
                    // 处理直接的数字比较
                    $field = $matches[1];
                    $value = (int)preg_replace('/[^0-9]/', '', $condition);
                    
                    if (!isset($row[$field]) || $row[$field] < $value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*<={0,1}\s*\d+/', $condition, $matches)) {
                    // 处理直接的数字比较
                    $field = $matches[1];
                    $value = (int)preg_replace('/[^0-9]/', '', $condition);
                    
                    if (!isset($row[$field]) || $row[$field] > $value) {
                        $andMatch = false;
                        break;
                    }
                }
            }
            
            if ($andMatch) {
                return true;
            }
        }
        
        return false;
    }
    
    private function splitConditions($condition, $delimiter) {
        $parts = [];
        $current = '';
        $inQuotes = false;
        $inParentheses = 0;
        
        for ($i = 0; $i < strlen($condition); $i++) {
            $char = $condition[$i];
            
            if ($char == '"' || $char == "'") {
                $inQuotes = !$inQuotes;
            } elseif ($char == '(') {
                $inParentheses++;
            } elseif ($char == ')') {
                $inParentheses--;
            } elseif (strtoupper(substr($condition, $i, strlen($delimiter))) == $delimiter && !$inQuotes && $inParentheses == 0) {
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
    
    private function getParamIndex($where, $condition) {
        $paramIndex = 0;
        $conditionPos = strpos($where, $condition);
        
        for ($i = 0; $i < $conditionPos; $i++) {
            if ($where[$i] == '?') {
                $paramIndex++;
            }
        }
        
        return $paramIndex;
    }
}

class JsonQuery {
    private $sql;
    private $params;
    private $storage;
    private $result;
    
    public function __construct($sql, $params, $storage) {
        $this->sql = $sql;
        $this->params = $params;
        $this->storage = $storage;
        $this->execute();
    }
    
    private function execute() {
        // 解析SQL语句
        if (preg_match('/SELECT\s+(.*?)\s+FROM\s+`?([^`\s]+)`?/i', $this->sql, $matches)) {
            $fields = $matches[1];
            $table = $matches[2];
            
            // 特殊处理COUNT(*)
            if (strtoupper(trim($fields)) === 'COUNT(*)') {
                // 提取WHERE条件
                $where = '';
                if (preg_match('/WHERE\s+(.*?)(?:\s+(ORDER|GROUP|LIMIT))?/i', $this->sql, $whereMatches)) {
                    $where = $whereMatches[1];
                }
                
                // 加载表数据
            $tableFile = $this->storage->getTableFile($table);
            $dataList = [];
            if (file_exists($tableFile)) {
                $content = file_get_contents($tableFile);
                $data = json_decode($content, true) ?: [];
                // 使用data字段的内容，如果不存在则使用整个数据
                $dataList = isset($data['data']) ? $data['data'] : $data;
            }
                
                // 应用WHERE条件
                $filteredData = [];
                foreach ($dataList as $row) {
                    if ($this->matchRowWhere($row, $where, $this->params)) {
                        $filteredData[] = $row;
                    }
                }
                
                // 返回计数结果
                $this->result = [[count($filteredData)]];
                return;
            }
            
            // 提取WHERE条件
            $where = '';
            if (preg_match('/WHERE\s+(.*?)(?:\s+(ORDER|GROUP|LIMIT))?/i', $this->sql, $whereMatches)) {
                $where = $whereMatches[1];
            }
            
            // 提取ORDER BY
            $order = '';
            if (preg_match('/ORDER\s+BY\s+(.*?)(?:\s+(LIMIT))?/i', $this->sql, $orderMatches)) {
                $order = $orderMatches[1];
            }
            
            // 提取LIMIT
            $limit = null;
            $offset = 0;
            
            // 处理命名参数的LIMIT
            if (preg_match('/LIMIT\s*:([\w_]+)\s*,\s*:([\w_]+)/i', $this->sql, $limitMatches)) {
                $offsetParam = $limitMatches[1];
                $limitParam = $limitMatches[2];
                if (isset($this->params[$offsetParam])) {
                    $offset = (int)$this->params[$offsetParam];
                }
                if (isset($this->params[$limitParam])) {
                    $limit = (int)$this->params[$limitParam];
                }
            } 
            // 处理数字的LIMIT
            elseif (preg_match('/LIMIT\s+(\d+)(?:\s*,\s*(\d+))?/i', $this->sql, $limitMatches)) {
                $limit = (int)$limitMatches[1];
                $offset = isset($limitMatches[2]) ? (int)$limitMatches[2] : 0;
            }
            
            // 加载表数据
            $tableFile = $this->storage->getTableFile($table);
            $dataList = [];
            if (file_exists($tableFile)) {
                $content = file_get_contents($tableFile);
                $data = json_decode($content, true) ?: [];
                // 使用data字段的内容，如果不存在则使用整个数据
                $dataList = isset($data['data']) ? $data['data'] : $data;
            }
            
            // 应用WHERE条件
            $filteredData = [];
            foreach ($dataList as $row) {
                if ($this->matchRowWhere($row, $where, $this->params)) {
                    $filteredData[] = $row;
                }
            }
            
            // 应用ORDER BY
            if (!empty($order)) {
                usort($filteredData, function($a, $b) use ($order) {
                    $orderParts = explode(',', $order);
                    foreach ($orderParts as $orderPart) {
                        $orderPart = trim($orderPart);
                        if (preg_match('/(\w+)\s+(ASC|DESC)/i', $orderPart, $orderMatch)) {
                            $field = $orderMatch[1];
                            $direction = strtoupper($orderMatch[2]);
                        } else {
                            $field = $orderPart;
                            $direction = 'ASC';
                        }
                        
                        if (!isset($a[$field]) || !isset($b[$field])) {
                            continue;
                        }
                        
                        if ($a[$field] < $b[$field]) {
                            return $direction === 'ASC' ? -1 : 1;
                        } elseif ($a[$field] > $b[$field]) {
                            return $direction === 'ASC' ? 1 : -1;
                        }
                    }
                    return 0;
                });
            }
            
            // 应用LIMIT和OFFSET
            if ($limit !== null) {
                $filteredData = array_slice($filteredData, $offset, $limit);
            }
            
            // 确保返回的是正确的数组结构
            $this->result = $filteredData;
        }
    }
    
    public function fetch() {
        return array_shift($this->result);
    }
    
    public function fetchAll() {
        return $this->result;
    }
    
    public function fetchColumn() {
        if (!empty($this->result)) {
            $firstRow = reset($this->result);
            return reset($firstRow);
        }
        return false;
    }
    
    private function matchRowWhere($row, $where, $params = []) {
        if (empty($where)) {
            return true;
        }
        
        // 处理OR逻辑
        $orConditions = $this->splitConditions($where, 'OR');
        
        foreach ($orConditions as $orCondition) {
            $andConditions = $this->splitConditions($orCondition, 'AND');
            $andMatch = true;
            
            foreach ($andConditions as $condition) {
                $condition = trim($condition);
                
                if (preg_match('/`?(\w+)`?\s*=\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] != (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*=\s*:([\w_]+)/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramName = $matches[2];
                    $value = isset($params[$paramName]) ? $params[$paramName] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] != (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*LIKE\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || !fnmatch(str_replace('%', '*', $value), $row[$field])) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*!=\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || (string)$row[$field] == (string)$value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*>\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || $row[$field] <= $value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*<\s*\?/', $condition, $matches)) {
                    $field = $matches[1];
                    $paramIndex = $this->getParamIndex($where, $condition);
                    $value = isset($params[$paramIndex]) ? $params[$paramIndex] : null;
                    
                    if (!isset($row[$field]) || $row[$field] >= $value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*>={0,1}\s*\d+/', $condition, $matches)) {
                    // 处理直接的数字比较
                    $field = $matches[1];
                    $value = (int)preg_replace('/[^0-9]/', '', $condition);
                    
                    if (!isset($row[$field]) || $row[$field] < $value) {
                        $andMatch = false;
                        break;
                    }
                } elseif (preg_match('/`?(\w+)`?\s*<={0,1}\s*\d+/', $condition, $matches)) {
                    // 处理直接的数字比较
                    $field = $matches[1];
                    $value = (int)preg_replace('/[^0-9]/', '', $condition);
                    
                    if (!isset($row[$field]) || $row[$field] > $value) {
                        $andMatch = false;
                        break;
                    }
                }
            }
            
            if ($andMatch) {
                return true;
            }
        }
        
        return false;
    }
    
    private function splitConditions($condition, $delimiter) {
        $parts = [];
        $current = '';
        $inQuotes = false;
        $inParentheses = 0;
        
        for ($i = 0; $i < strlen($condition); $i++) {
            $char = $condition[$i];
            
            if ($char == '"' || $char == "'") {
                $inQuotes = !$inQuotes;
            } elseif ($char == '(') {
                $inParentheses++;
            } elseif ($char == ')') {
                $inParentheses--;
            } elseif (strtoupper(substr($condition, $i, strlen($delimiter))) == $delimiter && !$inQuotes && $inParentheses == 0) {
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
    
    private function getParamIndex($where, $condition) {
        $paramIndex = 0;
        $conditionPos = strpos($where, $condition);
        
        for ($i = 0; $i < $conditionPos; $i++) {
            if ($where[$i] == '?') {
                $paramIndex++;
            }
        }
        
        return $paramIndex;
    }
}