# MySQL Database Synchronization

Esta librería es una solución para sincronizar datos entre dos bases de datos `MySQL`. Diseñada con un enfoque en la integridad referencial y el rendimiento, esta herramienta es ideal para escenarios de migración de datos, replicación o mantenimiento de bases de datos espejo.

## Características principales

- Dirección del la sincronización configurable (ASC o DESC)
- Resolución automática de dependencias entre tablas
- Manejo de inserciones en modo bulk para optimizar el rendimiento
- Soporte para transacciones para garantizar la integridad de los datos
- Sistema de logging flexible y personalizable
- Manejo inteligente de conflictos de claves únicas y violaciones de claves foráneas

## Instalación

Puedes instalar esta librería vía Composer:

```bash
composer require dankkomcg/mysql-sync
```

## Uso básico

```php
use Dankkomcg\MySQL\Sync\SyncManager;
use Dankkomcg\MySQL\Sync\Mappers\DisplayConsoleLog;

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

// Inicializar el SyncManager
$syncManager = new SyncManager(
    $sourceConfig,
    $targetConfig,
    1000, // Tamaño del chunk
    null, // Máximo de registros por tabla (null para sin límite)
    'DESC' // Dirección de sincronización
);

// Configurar el logger (opcional)
SyncManager::setLogger(new DisplayConsoleLog());

// Ejecutar la sincronización
$syncManager->run();
```

## Configuración avanzada

### Personalización del logging

Puedes implementar tu propio logger extendiendo la interfaz `LoggerInterface`:

```php
use Dankkomcg\MySQL\Sync\Mappers\LoggerInterface;

class CustomLogger implements LoggerInterface
{
    // Implementa los métodos requeridos
}

SyncManager::setLogger(new CustomLogger());
```

### Manejo de dependencias cíclicas

La librería utiliza un `DependencyResolver` para manejar dependencias entre tablas. En caso de dependencias cíclicas, se emitirá una advertencia y se procederá con un orden best-effort.

## Consideraciones de rendimiento

- El tamaño del chunk puede ajustarse para optimizar el rendimiento según las características de tus datos y recursos del sistema.
- La sincronización utiliza transacciones para garantizar la consistencia de los datos, lo que puede afectar el rendimiento en bases de datos muy grandes.

## Limitaciones conocidas

- Actualmente, solo soporta bases de datos MySQL.
- No sincroniza la estructura de las tablas, solo los datos.

## Contribuciones

Las contribuciones son bienvenidas. Por favor, abre un issue para discutir cambios mayores antes de enviar un pull request.

## Licencia

Este proyecto está licenciado bajo la Licencia MIT.