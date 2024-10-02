# MySQL Database Synchronization

Esta librería es una solución para sincronizar datos entre dos bases de datos `MySQL`. 
Diseñada con un enfoque en la integridad referencial, esta librería 
permite escenarios de migración de datos, replicación o mantenimiento de bases de datos en forma de espejo.

## Características principales

- Dirección del la sincronización configurable (ASC o DESC)
- Resolución automática de dependencias entre tablas
- Manejo de inserciones en modo bulk para optimizar el rendimiento
- Soporte para transacciones para garantizar la integridad de los datos
- Sistema de logging flexible y personalizable
- Manejo conflictos de claves únicas y violaciones de claves foráneas
- Seleccionar tablas para la sincronización

## Instalación

Puedes instalar esta librería vía Composer:

```bash
composer require dankkomcg/mysql-sync
```

## Uso básico

```php
use Dankkomcg\MySQL\Sync\Loggers\ConsoleLogger;
use Dankkomcg\MySQL\Sync\SyncManager;

// Configuración de las bases de datos
$sourceConfig = [
    'host' => 'localhost',
    'dbname' => 'source_database',
    'user' => 'username',
    'password' => 'password'
];

$targetConfig = [
    'host' => 'localhost',
    'dbname' => 'target_database',
    'user' => 'username',
    'password' => 'password'
];

$sourceConnection = new DatabaseConnection($sourceHost, $sourceUsername, $sourcePassword, $sourceSchema);
$targetConnection = new DatabaseConnection($targetHost, $targetUsername, $targetPassword, $targetSchema);

$syncManager      = new SyncManager($sourceConnection, $targetConnection);

// The chunk size and the query order are required
$syncManager->setChunkSize(1000);
$syncManager->setQueryOrder('DESC');

// Records per table its optional
$syncManager->setMaxRecordsPerTable(1000);

// Run the synchronization from schemas
$syncManager->run($sourceSchema, $targetSchema);
```

## Configuración avanzada

### Custom logger

Se puede implementar un custom logger extendiendo la interfaz `Logger`:

```php
use Dankkomcg\MySQL\Sync\Loggers\Logger;

class CustomLogger implements Logger {
    // Implementa los métodos requeridos
}

$syncManager->setLogger(new CustomLogger());
```

### Composite logger

```php
// Config the logger
$compositeLogger = new CompositeLogger();
$compositeLogger->addLogger(new ConsoleLogger);
$compositeLogger->addLogger(
    new FileLogger(
        sprintf(
            "synchronize_database_%s.log", date('Ymd_His')
        )
    )
);

$syncManager->setLogger($compositeLogger);
```

### Manejo de dependencias cíclicas

La librería utiliza un `DependencyResolver` para manejar dependencias entre tablas. 
En caso de dependencias cíclicas, se emitirá una advertencia y se procederá con un orden `best-effort`.

## Consideraciones de rendimiento

- El tamaño del chunk puede ajustarse para optimizar el rendimiento según las características de tus datos y recursos del sistema.
- La sincronización utiliza transacciones para garantizar la consistencia de los datos, lo que puede afectar el rendimiento en bases de datos muy grandes.

## Limitaciones conocidas

- Actualmente, solo soporta bases de datos MySQL.
- No sincroniza la estructura de las tablas, solo los datos.

## Contribuciones

Las contribuciones son bienvenidas. Por favor, abre un issue para discutir cambios antes de enviar un pull request.

## Licencia

Este proyecto está licenciado bajo Licencia MIT.