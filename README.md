# MySQL Sync: MySQL Database Synchronization Library

Esta librería proporciona una solución para sincronizar datos entre dos bases de datos `MySQL`. 
Está diseñada para manejar grandes volúmenes de datos, respetar las dependencias entre tablas y resolver inconsistencias durante la sincronización.

## Características destacables

- Sincronización basada en el orden temporal (ASC o DESC) configurable.
- Resolución automática de dependencias entre tablas.
- Manejo de inserciones en modo bulk para mejorar el rendimiento.
- Estrategias de fallback para manejar conflictos de claves únicas y violaciones de claves foráneas.
- Soporte para sincronización parcial con límite de registros por tabla.

## Uso básico

```php
$sourceConfig = [
    'host' => 'source_host',
    'dbname' => 'source_db',
    'user' => 'source_user',
    'password' => 'source_password'
];

$targetConfig = [
    'host' => 'target_host',
    'dbname' => 'target_db',
    'user' => 'target_user',
    'password' => 'target_password'
];

$syncManager = new SyncManager($sourceConfig, $targetConfig, 1000, null, 'DESC');
$syncManager->sync();
```

## Detalles de implementación

El siguiente diagrama de secuencia muestra el flujo de operaciones durante el proceso de sincronización:

```
SyncManager         DependencyResolver    TableSync           DatabaseConnection
    |                       |                  |                       |
    |   sync()              |                  |                       |
    |-------------------→   |                  |                       |
    |   getTablesInDependencyOrder()           |                       |
    |-------------------→   |                  |                       |
    |                       |                  |                       |
    |   ←-------------------┐                  |                       |
    |   return sortedTables |                  |                       |
    |                       |                  |                       |
    |   syncTables(sortedTables)               |                       |
    |-------------------------------------→    |                       |
    |                       |                  |   getPdo()            |
    |                       |                  |-------------------→   |
    |                       |                  |   ←-------------------┐
    |                       |                  |   return PDO          |
    |                       |                  |                       |
    |                       |                  |   fetchRows()         |
    |                       |                  |-------------------→   |
    |                       |                  |   ←-------------------┐
    |                       |                  |   return rows         |
    |                       |                  |                       |
    |                       |                  |   insertRows()        |
    |                       |                  |-------------------→   |
    |                       |                  |   ←-------------------┐
    |                       |                  |   return result       |
    |                       |                  |                       |
    |   ←-------------------------------------┐                        |
    |   return sync result  |                  |                       |
    |                       |                  |                       |
```

Este diagrama muestra como es el proceso de sincronización:

1. SyncManager inicia el proceso llamando a su método sync().
2. Se utiliza DependencyResolver para obtener el orden de las tablas basado en sus dependencias.
3. SyncManager llama a TableSync para sincronizar las tablas en el orden determinado.
4. TableSync interactúa con DatabaseConnection para obtener conexiones PDO y realizar operaciones en las bases de datos.
5. Para cada tabla, TableSync realiza las operaciones de fetchRows() e insertRows().
6. Finalmente, SyncManager devuelve el resultado de la sincronización.

## Manejo de errores y logging

La librería implementa un sistema robusto de manejo de errores y logging, permitiendo una fácil identificación y resolución de problemas durante el proceso de sincronización.

## Consideraciones de rendimiento

- Utiliza inserciones en modo bulk para mejorar significativamente el rendimiento.
- Implementa estrategias de fallback para manejar conflictos sin detener el proceso.
- Permite la configuración del tamaño de chunk y el número máximo de registros por tabla para optimizar el uso de recursos.

## Limitaciones y posibles mejoras

- Actualmente solo soporta MySQL. Se podría extender para otros SGBD en el futuro.
- No maneja la sincronización de esquemas de base de datos, solo datos.
- Podría beneficiarse de un sistema de logging más detallado y configurable.

## Contribuciones

Las contribuciones son bienvenidas. Por favor, abre una issue para discutir cambios mayores antes de enviar un pull request.

