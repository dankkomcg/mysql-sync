# MySQL Database Synchronization

Esta librería es una solución para sincronizar datos entre dos bases de datos `MySQL`. 
Diseñada con un enfoque en la integridad referencial, esta librería 
permite escenarios de migración de datos, replicación o mantenimiento de bases de datos en forma de espejo.

# Características principales

- Dirección del la sincronización configurable (ASC o DESC)
- Resolución automática de dependencias entre tablas
- Manejo de inserciones en modo bulk para optimizar el rendimiento
- Soporte para transacciones para garantizar la integridad de los datos
- Sistema de logging flexible y personalizable
- Manejo conflictos de claves únicas y violaciones de claves foráneas
- Seleccionar tablas para la sincronización

# Instalación

Puedes instalar esta librería vía Composer:

```bash
composer require dankkomcg/mysql-sync
```

# First Application Walkthrough

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

# Manejo de dependencias cíclicas

La librería tiene en cuenta el orden de importación de las tablas antes de sincronizar los datos 
con el objetivo determinar un orden seguro para operaciones como inserción de datos o creación de tablas, 
lo que asegura que las dependencias se manejen correctamente.

## TopologicalDependencyResolver

El ordenamiento topológico es un algoritmo utilizado para ordenar elementos en un grafo dirigido de manera que 
para cada arista dirigida del nodo A al nodo B, A aparece antes que B en el ordenamiento. En nuestro caso, 
los nodos son tablas y las aristas son las relaciones de clave foránea. De esta manera, las dependencias se respetan, 
colocando las tablas independientes primero y las dependientes después mediante el criterio de ordenación `best-effort`.

### Conceptos

1. **Grado de entrada**: Es el número de aristas que apuntan a un nodo. En términos de base de datos, es el número de tablas que tienen una clave foránea que referencia a esta tabla.

Al **reducir del grado de entrada** cuando procesamos una tabla, estamos diciendo "esta tabla ya está ordenada". Por lo tanto, para todas las tablas que dependen de ella, reducimos su contador de dependencias (grado de entrada) en 1.
Cuando su **grado de entrada llega a 0**, significa que ya hemos procesado todas las tablas de las que dependía. Por lo tanto, ahora podemos añadirla a la cola para ser procesada.

2. **Cola**: Una estructura de datos que almacena elementos en orden de llegada.

### Algoritmo:

1. **Inicialización**:
    - Se crea una cola con todas las tablas que tienen grado de entrada 0. Estas son tablas que no dependen de ninguna otra.

2. **Proceso principal**:
    - Mientras la cola no esté vacía:
      a. Se saca una tabla de la cola.
      b. Se añade esta tabla a la lista de tablas ordenadas.
      c. Para cada tabla que depende de la tabla actual:
        - Se reduce su grado de entrada en 1.
        - Si su grado de entrada llega a 0, se añade a la cola.

3. **Verificación de ciclos**:
    - Si quedan tablas no procesadas, significa que hay un ciclo.

4. **Inversión del resultado**:
    - Se invierte la lista final para que las tablas dependientes estén al final.

### Ejemplo simplificado:

Imaginemos tres tablas: A, B y C. B depende de A, y C depende de B.

1. Inicialmente:
    - A tiene grado de entrada 0
    - B tiene grado de entrada 1 (depende de A)
    - C tiene grado de entrada 1 (depende de B)

2. Proceso:
    - A se añade a la cola inicialmente (grado 0)
    - Procesamos A:
        - A se añade a la lista ordenada
        - Reducimos el grado de B de 1 a 0
        - B se añade a la cola (ahora su grado es 0)
    - Procesamos B:
        - B se añade a la lista ordenada
        - Reducimos el grado de C de 1 a 0
        - C se añade a la cola
    - Procesamos C:
        - C se añade a la lista ordenada

3. Resultado final (invertido): C, B, A

Este orden asegura que las tablas dependientes (como C) se procesen después de las tablas de las que dependen (como A y B).

## DynamicDependencyResolver

`TopologicalDependencyResolver` presenta un problema lógico
que está estrictamente relacionado con diseños complejos 
de base de datos donde se producen ciclos en las dependencias 
entre tablas al no poder determinar qué tabla debe crearse primero, 
ya que cada una depende de las otras.

Imaginemos una base de datos con las siguientes tablas y relaciones:

1. **empleados**: Tabla principal de empleados.
2. **departamentos**: Tabla de departamentos.
3. **proyectos**: Tabla de proyectos.

Ahora, supongamos que tenemos estas relaciones:

- La tabla **empleados** tiene una clave foránea que referencia a **departamentos** (cada empleado pertenece a un departamento).
- La tabla **departamentos** tiene una clave foránea que referencia a **empleados** (cada departamento tiene un jefe, que es un empleado).
- La tabla **proyectos** tiene una clave foránea que referencia a **empleados** (cada proyecto tiene un líder).
- La tabla **empleados** tiene una clave foránea que referencia a **proyectos** (cada empleado está asignado a un proyecto principal).

Si utilizamos `TopologicalDependencyResolver`:

1. Inicialmente:
    - empleados: grado de entrada 2 (depende de departamentos y proyectos)
    - departamentos: grado de entrada 1 (depende de empleados)
    - proyectos: grado de entrada 1 (depende de empleados)

2. Proceso:
    - Ninguna tabla tiene grado de entrada 0, por lo que la cola inicial está vacía.
    - El algoritmo no puede comenzar porque no hay tablas sin dependencias.

3. Resultado:
    - El algoritmo detecta que no puede ordenar todas las tablas.
    - Las tablas se agregan al final en el orden en que se encuentran.

En la práctica, este tipo de ciclos suelen resolverse de las siguientes maneras:

1. Rediseñar la estructura de la base de datos para eliminar el ciclo.
2. Usar claves foráneas que permitan valores NULL inicialmente.
3. Crear las tablas sin las restricciones de clave foránea, insertar los datos y luego añadir las restricciones.

El algoritmo en la clase `TopologicalDependencyResolver` maneja esta situación agregando las tablas con dependencias cíclicas al final del orden, pero es importante notar que esto no resuelve completamente el problema de las dependencias circulares en la base de datos real.

```text
hay un punto clave con el algoritmo de TopologicalDependencyResolver, y me indicas lo siguiente: Usar claves foráneas que permitan valores NULL inicialmente.
se te ocurre como podrías darle un orden basándote en claves foráneas que tengan valores null por defecto? Crees que sigues dependiendo de los datos como hiciste en algoritmo DynamicDependencyResolver?
```

# Filtrar tablas

El método `setFilteredTables(array $tables)` permite especificar qué tablas sincronizar, ofreciendo un control más granular sobre el proceso de sincronización.
Simplemente setear el método e indicar las tablas a sincronizar:

```php
$syncManager->setFilteredTables(['users', 'clients']);
```

Debemos tener en cuenta que estas tablas pueden tener dependencias respecto a otras tablas, por lo tanto, 
deberíamos sincronizar las tablas dependientes.

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