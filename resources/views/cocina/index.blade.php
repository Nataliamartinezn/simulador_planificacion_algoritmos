<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina Concurrente</title>
    <link rel="stylesheet" href="{{ asset('css/cocina.css') }}">
</head>
<body>
    <header class="encabezado">
        <h1>Cocina Concurrente</h1>
        <p>Simulacion de preparacion secuencial y paralela de un pedido</p>
    </header>

    <main class="contenedor">
        @if ($errors->any())
            <section class="alerta">
                <h2>Revisa el pedido</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="panel">
            <div class="panel-titulo">
                <div>
                    <p class="etiqueta">Comanda</p>
                    <h2>Pedido de cocina</h2>
                </div>
                <button class="boton boton-secundario" type="button" data-agregar-preparacion>+ Agregar preparacion</button>
            </div>

            <form action="{{ route('cocina.ejecutar') }}" method="POST">
                @csrf
                <input type="hidden" name="ciclo_ejecucion" value="{{ $cicloEjecucion }}">

                <div class="tickets" data-lista-preparaciones>
                    @foreach ($preparaciones as $indice => $preparacion)
                        <article class="ticket" data-ticket>
                            <div class="ticket-cabecera">
                                <span>PREPARACION</span>
                                <strong>#<span data-numero-ticket>{{ $indice + 1 }}</span></strong>
                            </div>

                            <label>
                                Nombre
                                <input type="text" name="nombres[]" value="{{ old('nombres.'.$indice, $preparacion->getNombre()) }}" required>
                            </label>

                            <label>
                                Duracion en segundos
                                <input type="number" name="duraciones[]" min="1" max="20" value="{{ old('duraciones.'.$indice, $preparacion->getDuracion()) }}" required>
                            </label>

                            <button class="boton boton-peligro" type="button" data-eliminar-preparacion>Eliminar</button>
                        </article>
                    @endforeach
                </div>

                <div class="acciones">
                    <button class="boton boton-principal" type="submit" name="modo" value="secuencial">Ejecutar secuencial</button>
                    <button class="boton boton-principal" type="submit" name="modo" value="paralelo">Ejecutar paralelo</button>
                </div>
            </form>
        </section>

        @if (! empty($resultadosGuardados))
            @foreach ($resultadosGuardados as $resultadoGuardado)
                <section class="panel resultado">
                    <p class="etiqueta">Resultado de la ejecucion</p>
                    <h2>Modo: {{ $resultadoGuardado['modo'] }}</h2>
                    <p class="tiempo-total">Tiempo total: <strong>{{ number_format($resultadoGuardado['tiempo_total'], 2) }} segundos</strong></p>

                    <div class="tickets tickets-resultados">
                        @foreach ($resultadoGuardado['resultados'] as $item)
                            <article class="ticket ticket-resultado">
                                <div class="ticket-cabecera">
                                    <span>PROCESO #{{ $item['proceso'] }}</span>
                                    <strong>{{ $item['estado'] }}</strong>
                                </div>

                                <h3>{{ $item['nombre'] }}</h3>
                                <p>Duracion programada: <strong>{{ $item['duracion'] }} s</strong></p>
                                <p>Tiempo real: <strong>{{ number_format((float) $item['tiempo_real'], 2) }} s</strong></p>

                                <p class="estado {{ $item['estado'] === 'LISTO' ? 'estado-listo' : 'estado-error' }}">
                                    {{ $item['estado'] === 'LISTO' ? '🟢 LISTO' : '🔴 ERROR' }}
                                </p>

                                @if (! empty($item['mensaje']))
                                    <p class="mensaje-error">{{ $item['mensaje'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif

        @if ($comparacion)
            <section class="panel comparacion">
                <p class="etiqueta">Comparacion</p>
                <h2>Secuencial vs paralelo</h2>
                <div class="comparacion-grid">
                    <article>
                        <span>Secuencial</span>
                        <strong>{{ number_format($comparacion['secuencial'], 2) }} s</strong>
                    </article>
                    <article>
                        <span>Paralelo</span>
                        <strong>{{ number_format($comparacion['paralelo'], 2) }} s</strong>
                    </article>
                    <article>
                        <span>Diferencia</span>
                        <strong>{{ number_format($comparacion['diferencia'], 2) }} s</strong>
                    </article>
                    <article>
                        <span>Mejora</span>
                        <strong>{{ number_format($comparacion['mejora'], 2) }} %</strong>
                    </article>
                </div>
            </section>
        @endif
    </main>

    <script src="{{ asset('js/cocina.js') }}"></script>
</body>
</html>
