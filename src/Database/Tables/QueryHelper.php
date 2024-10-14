<?php

namespace Dankkomcg\MySQL\Sync\Database\Tables;

class QueryHelper {

    public const INFORMATION_SCHEMA_TABLES_QUERY =
        "SELECT 
                TABLE_NAME 
            FROM 
                INFORMATION_SCHEMA.TABLES 
            WHERE 
                TABLE_SCHEMA = :schema AND TABLE_TYPE = 'BASE TABLE'
                "
    ;

    public const INFORMATION_SCHEMA_TABLES_QUERY_FILTERED =
        "SELECT 
                TABLE_NAME 
            FROM 
                INFORMATION_SCHEMA.TABLES 
            WHERE 
                TABLE_SCHEMA = :schema AND TABLE_TYPE = 'BASE TABLE'
                AND TABLE_NAME IN (%s)
                "
    ;

    public const REFERENCED_PARENT_TABLE_NAME_QUERY =
        "SELECT
                TABLE_NAME, REFERENCED_TABLE_NAME
            FROM
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE
                TABLE_SCHEMA = :schema AND REFERENCED_TABLE_SCHEMA = :schema
                AND REFERENCED_TABLE_NAME IS NOT NULL
                AND TABLE_NAME IN (%s)
                "
    ;

    public const FOREIGN_KEY_PATTERN_TABLE =
        "SELECT 
            TABLE_NAME, 
            CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, COLUMN_NAME
         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
         WHERE TABLE_SCHEMA = :schema
         AND 
            TABLE_NAME = :table_name AND 
            REFERENCED_TABLE_NAME IS NOT NULL
        ;"
    ;

    public const QUERY_PARENT_TABLES_FOREIGN_KEY =
        "
            SELECT DISTINCT REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = :schema
            AND TABLE_NAME = :table_name
            AND REFERENCED_TABLE_NAME IS NOT NULL
        "
    ;

}