<?php

namespace App\Storage;

interface StorageInterface {
    public function prepare($sql);
    public function execute($sql, $params = []);
    public function query($sql, $params = []);
    public function fetch($sql, $params = []);
    public function fetchAll($sql, $params = []);
    public function fetchColumn($sql, $params = []);
    public function insert($table, $data);
    public function update($table, $data, $where, $whereParams = []);
    public function delete($table, $where, $whereParams = []);
    public function lastInsertId();
    public function createDatabase($dbname);
    public function useDatabase($dbname);
}