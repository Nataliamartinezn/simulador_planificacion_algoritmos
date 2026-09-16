<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Simulador de Planificacion</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <link rel="stylesheet" href="{{ asset('css/scheduler.css') }}">
    <script src="{{ asset('js/scheduler.js') }}" defer></script>
</head>
<body>
<div class="page-shell">
    <main class="content">
        <section class="intro-card" aria-labelledby="intro-title">
            <div>
                <h2 id="intro-title">Planificacion de procesos</h2>
                <p>
                    Selecciona un algoritmo, carga procesos parametrizables y compara los resultados
                    mediante metricas y diagrama de Gantt.
                </p>
            </div>

            <div id="simulation-status" class="status-badge is-ready" aria-live="polite" aria-label="Estado actual">
                <span class="status-dot"></span>
                <span id="simulation-status-text">Listo</span>
            </div>
        </section>

        <form id="scheduler-form" class="panel" data-simulate-url="{{ route('scheduler.simulate') }}">
            @csrf

            <div id="error-box" class="error hidden" role="alert"></div>

            <section id="configuracion" class="panel-section" aria-labelledby="config-title">
                <div class="section-header">
                    <div>
                        <span class="section-label">Configuracion</span>
                        <h2 id="config-title">Algoritmo</h2>
                    </div>
                </div>

                <div class="algorithm-tabset">
                    <div class="algorithm-tabs" aria-label="Algoritmos disponibles">
                        <label class="algorithm-tab">
                            <input type="radio" name="algorithm" value="fcfs" checked>
                            <span>FCFS / FIFO</span>
                        </label>
                        <label class="algorithm-tab">
                            <input type="radio" name="algorithm" value="sjf">
                            <span>SJF</span>
                        </label>
                        <label class="algorithm-tab">
                            <input type="radio" name="algorithm" value="rr">
                            <span>Round Robin</span>
                        </label>
                        <label class="algorithm-tab">
                            <input type="radio" name="algorithm" value="priority">
                            <span>Prioridad</span>
                        </label>
                        <label class="algorithm-tab">
                            <input type="radio" name="algorithm" value="mlfq">
                            <span>MLFQ</span>
                        </label>
                    </div>

                    <div id="algorithm-info" class="algorithm-description"></div>
                </div>

                <div class="parameter-grid">
                    <div id="rr-options" class="field-card hidden">
                        <label for="quantum">Quantum Round Robin</label>
                        <input id="quantum" type="number" name="quantum" min="1" value="2">
                    </div>

                    <div id="priority-options" class="field-card hidden">
                        <label for="priority_order">Criterio de prioridad</label>
                        <select id="priority_order" name="priority_order">
                            <option value="lower">Menor valor</option>
                            <option value="higher">Mayor valor</option>
                        </select>
                    </div>

                    <div id="mlfq-options" class="field-card field-card-wide hidden">
                        <div class="parameter-grid">
                            <div>
                                <label for="mlfq_quantums">Quantum por cola MLFQ</label>
                                <input id="mlfq_quantums" type="text" name="mlfq_quantums" value="2,4,8" placeholder="Ejemplo: 2,4,8">
                                <p class="hint">Cada valor representa una cola: Q1, Q2, Q3...</p>
                            </div>
                            <div>
                                <label for="boost_interval">Intervalo de priority boost</label>
                                <input id="boost_interval" type="number" name="boost_interval" min="0" value="20">
                                <p class="hint">0 desactiva el boost.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="procesos" class="panel-section panel-section-bordered" aria-labelledby="process-title">
                <div class="section-header">
                    <div>
                        <span class="section-label">Entrada</span>
                        <h2 id="process-title">Procesos</h2>
                    </div>
                    <div class="icon-actions">
                        <button type="button" id="add-process" class="icon-action icon-action-primary" aria-label="Agregar proceso" title="Agregar proceso">
                            <span class="material-symbols-outlined" aria-hidden="true">add</span>
                        </button>
                        <button type="button" id="clear-processes" class="icon-action icon-action-danger" aria-label="Limpiar procesos" title="Limpiar procesos">
                            <span class="material-symbols-outlined" aria-hidden="true">delete_sweep</span>
                        </button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="process-table">
                        <thead>
                        <tr>
                            <th>Proceso</th>
                            <th>Llegada</th>
                            <th>Rafaga CPU</th>
                            <th class="priority-column">Prioridad</th>
                            <th>Accion</th>
                        </tr>
                        </thead>
                        <tbody id="process-body"></tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Simular</button>
                </div>
            </section>
        </form>

        <section id="results" class="panel results-panel hidden" aria-live="polite">
            <div class="section-header">
                <div>
                    <span class="section-label">Resultados</span>
                    <h2 id="result-title"></h2>
                    <div id="result-parameters" class="hint"></div>
                </div>
                <span id="result-status" class="result-badge is-passed">passed</span>
            </div>

            <div id="metrics" class="metrics"></div>

            <section class="result-section" aria-labelledby="gantt-title">
                <h3 id="gantt-title">Diagrama de Gantt</h3>
                <div class="gantt-scroll">
                    <div id="gantt" class="gantt"></div>
                </div>
            </section>

            <section class="result-section" aria-labelledby="metrics-title">
                <h3 id="metrics-title">Metricas por proceso</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Proceso</th>
                            <th>Llegada</th>
                            <th>Rafaga</th>
                            <th class="priority-result-column">Prioridad</th>
                            <th>Primer inicio</th>
                            <th>Finalizacion</th>
                            <th>Retorno</th>
                            <th>Espera</th>
                            <th>Respuesta</th>
                        </tr>
                        </thead>
                        <tbody id="result-body"></tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>

    <footer class="site-footer">
        <span>Simulador de Planificacion de Procesos</span>
        <span>Sistemas operativos · Natalia C. Martínez</span>
    </footer>
</div>
</body>
</html>
