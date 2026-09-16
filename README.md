# Simulador de algoritmos de planificación - Laravel

Actividad 2: simulador parametrizable de planificación de procesos implementado en PHP con Laravel.

## Algoritmos incluidos

- FCFS / FIFO (no expropiativo)
- SJF (no expropiativo)
- Round Robin (quantum configurable)
- Prioridad (no expropiativo; se puede elegir si el número menor o mayor representa mayor prioridad)
- MLFQ / Colas multinivel con retroalimentación (cantidad de niveles, quantum por nivel y priority boost configurables)

## Parametrización

Los procesos no están definidos dentro de los algoritmos. La interfaz permite agregar o eliminar filas y modificar:

- identificador del proceso;
- tiempo de llegada;
- ráfaga de CPU;
- prioridad;
- quantum de Round Robin;
- convención de prioridad;
- quantum de cada cola MLFQ, por ejemplo `2,4,8` o `1,2,4,8`;
- intervalo de `priority boost` de MLFQ (`0` lo desactiva).

Por eso se puede pasar de 5 a 10, 20 o más procesos sin modificar la lógica PHP.

## Requisitos

Este proyecto está preparado para Laravel 13.x, que requiere PHP 8.3 o superior.

También necesitas Composer.

## Instalación

Desde la carpeta del proyecto:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

En Windows PowerShell puedes copiar el archivo con:

```powershell
Copy-Item .env.example .env
```

Abre en el navegador la dirección que muestre `php artisan serve` (normalmente `http://127.0.0.1:8000`).

No se necesita base de datos para la simulación.

## Uso

1. Selecciona un algoritmo.
2. Agrega, elimina o edita procesos en la tabla.
3. Ajusta los parámetros que aparezcan para el algoritmo elegido.
4. Presiona **Simular**.
5. Revisa el diagrama de Gantt y las métricas.

## Métricas

Para cada proceso se calcula:

- **Finalización (CT):** instante en que termina.
- **Retorno (TAT):** `CT - tiempo de llegada`.
- **Espera (WT):** `TAT - ráfaga CPU`.
- **Respuesta (RT):** `primer inicio - tiempo de llegada`.

También se muestran promedios, tiempo final, utilización de CPU y throughput.

## Convenciones usadas

### SJF

Se implementa SJF no expropiativo. Cuando la CPU queda libre se elige, entre los procesos que ya llegaron, el de menor ráfaga. Los empates se resuelven por llegada y después por orden de entrada.

### Prioridad

Se implementa prioridad no expropiativa. La interfaz permite elegir si un número menor o un número mayor representa mayor prioridad. Los empates se resuelven por llegada y orden de entrada.

### MLFQ

- Todo proceso nuevo entra a Q1.
- Cada cola tiene un quantum configurable.
- Si un proceso consume todo el quantum y no termina, baja un nivel (en la última cola permanece allí).
- Si aparece un proceso nuevo mientras se ejecuta una cola inferior, el proceso actual es expropiado y permanece en su nivel.
- Si se configura `priority boost`, periódicamente todos los procesos listos regresan a Q1.
- El valor `0` desactiva el boost.

## Estructura principal

```text
app/
  Http/Controllers/SchedulerController.php
  Services/Scheduling/ProcessScheduler.php
resources/views/scheduler.blade.php
routes/web.php
tests/
  Unit/ProcessSchedulerTest.php
  run_manual.php
```

`ProcessScheduler.php` contiene toda la lógica de los algoritmos. El controlador valida la entrada y la vista Blade permite editar dinámicamente los procesos.

## Pruebas

Sin dependencias del framework puedes comprobar rápidamente la lógica central:

```bash
php tests/run_manual.php
```

Después de `composer install` también puedes ejecutar PHPUnit:

```bash
php artisan test
```

Las pruebas incluyen un caso que genera 10 procesos dinámicamente y ejecuta los cinco algoritmos sin cambios en el código.
