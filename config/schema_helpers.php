<?php

function db_identifier($name) {
    $name = (string)$name;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }
    return '`' . $name . '`';
}

function db_column_exists($conn, $table, $column) {
    $tableSql = db_identifier($table);
    $columnLike = $conn->real_escape_string((string)$column);
    $res = $conn->query("SHOW COLUMNS FROM $tableSql LIKE '$columnLike'");
    return $res && $res->num_rows > 0;
}

function db_add_column_if_missing($conn, $table, $column, $definition) {
    if (db_column_exists($conn, $table, $column)) {
        return true;
    }
    $tableSql = db_identifier($table);
    $columnSql = db_identifier($column);
    return $conn->query("ALTER TABLE $tableSql ADD COLUMN $columnSql $definition");
}
