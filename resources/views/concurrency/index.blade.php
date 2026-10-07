<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laboratorio de concurrencia</title>
    <link rel="stylesheet" href="{{ asset('css/concurrency.css') }}">
    <script src="{{ asset('js/concurrency.js') }}" defer></script>
</head>
<body>
<main class="lab-page">
    <header class="lab-header">
        <div>
            <h1>Laboratorio de concurrencia</h1>
            <p>Simulacion de acceso simultaneo a un recurso compartido</p>
        </div>
        <button id="new-simulation" class="button button-secondary" type="button">Nueva simulacion</button>
    </header>

    <section class="setup-card" aria-labelledby="setup-title">
        <div class="setup-intro">
            <h2 id="setup-title">Preparacion de la simulacion</h2>
            <p>Varios procesos intentan descontar stock del mismo inventario al mismo tiempo.</p>
        </div>

        <form id="concurrency-form" class="setup-form" data-run-url="{{ route('concurrency.run') }}" data-reset-url="{{ route('concurrency.reset') }}">
            @csrf
            <input id="product_id" type="hidden" name="product_id" value="{{ $products->first()?->id }}">

            <div class="setup-fields">
            <label>
                <span>Stock inicial</span>
                <input id="initial_stock" name="initial_stock" type="number" min="1" max="10000" value="20" required>
            </label>

            <label>
                <span>Numero de pedidos</span>
                <input id="order_count" name="order_count" type="number" min="1" max="50" value="3" required>
            </label>

            <label>
                <span>Cantidad por pedido</span>
                <input id="quantity_per_order" name="quantity_per_order" type="number" min="1" max="1000" value="2" required>
            </label>

            <label>
                <span>Numero de procesos</span>
                <input id="process_count" type="number" min="1" max="8" value="3">
            </label>
            </div>

            <div class="execution-row">
                <label>
                    <span>Tipo de sincronizacion</span>
                    <select id="selected-method" name="method" required>
                        <option value="none">Sin sincronizacion</option>
                        <option value="db_lock">Mutex</option>
                        <option value="optimistic_lock">Bloqueo optimista</option>
                    </select>
                </label>

                <button class="button button-primary" type="submit">Ejecutar</button>
            </div>
        </form>

        <div id="form-error" class="form-error" role="alert"></div>
    </section>

    <section class="lab-stage" aria-label="Zona principal del laboratorio">
        <aside class="inventory-card" aria-labelledby="inventory-title">
            <h2 id="inventory-title">Inventario compartido</h2>

            <div class="inventory-stock">
                <span>Stock actual</span>
                <strong id="shared-stock">-</strong>
                <small>unidades</small>
            </div>

            <div class="inventory-detail">
                <span>Producto</span>
                <strong id="shared-product">{{ $products->first()?->name ?? '-' }}</strong>
            </div>

            <div class="inventory-detail">
                <span>Estado del inventario</span>
                <strong id="resource-status"><i class="state-dot state-free"></i>Disponible</strong>
            </div>

            <div id="data-movement" class="data-movement">Movimientos de lectura y escritura</div>
        </aside>

        <div class="processes-panel">
            <div class="section-heading">
                <div>
                    <h2>Procesos</h2>
                    <p>Cada tarjeta representa un proceso independiente intentando descontar stock.</p>
                </div>
                <span id="run-status" class="status-pill">Sin ejecucion</span>
            </div>

            <div id="process-grid" class="process-grid">
                <div class="empty-state">Inicia una simulacion para ver los procesos en vivo.</div>
            </div>
        </div>
    </section>

    <section id="race-panel" class="race-panel" hidden>
        <h2>Condicion de carrera detectada</h2>
        <p>Varios procesos leyeron el mismo stock antes de que alguno guardara el descuento. Por eso se pierden actualizaciones.</p>
        <div id="race-readers" class="race-readers"></div>
    </section>

    <section id="result-panel" class="result-panel" hidden></section>

</main>
</body>
</html>
