const form = document.querySelector('#concurrency-form');
const selectedMethod = document.querySelector('#selected-method');
const resetButton = document.querySelector('#new-simulation');
const errorBox = document.querySelector('#form-error');
const runStatus = document.querySelector('#run-status');
const processGrid = document.querySelector('#process-grid');
const sharedStock = document.querySelector('#shared-stock');
const sharedProduct = document.querySelector('#shared-product');
const resourceStatus = document.querySelector('#resource-status');
const dataMovement = document.querySelector('#data-movement');
const racePanel = document.querySelector('#race-panel');
const raceReaders = document.querySelector('#race-readers');
const resultPanel = document.querySelector('#result-panel');
const csrf = document.querySelector('meta[name="csrf-token"]').content;

let pollTimer = null;
let lastStock = null;
let lastMovement = '';

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    await runSimulation();
});

resetButton.addEventListener('click', () => {
    clearInterval(pollTimer);
    errorBox.textContent = '';
    racePanel.hidden = true;
    resultPanel.hidden = true;
    selectedMethod.value = 'none';
    lastStock = null;
    lastMovement = '';
    setRunStatus('Sin ejecucion', '');
    processGrid.innerHTML = '<div class="empty-state">Inicia una simulacion para ver los procesos en vivo.</div>';
    sharedStock.textContent = '-';
    setResourceFree();
    dataMovement.textContent = 'Los movimientos de lectura y escritura apareceran aca.';
    dataMovement.classList.remove('is-write');
});

async function runSimulation() {
    errorBox.textContent = '';
    resultPanel.hidden = true;
    racePanel.hidden = true;
    setRunStatus('Simulacion en curso', 'is-running');

    const response = await postForm(form.dataset.runUrl, new FormData(form));

    if (!response.ok) {
        showError(response.data);
        setRunStatus('Error', 'is-bad');
        return;
    }

    startPolling(response.data.status_url);
}

async function postForm(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
        },
        body: data,
    });

    return { ok: response.ok, data: await response.json() };
}

function startPolling(url) {
    clearInterval(pollTimer);
    refreshStatus(url);
    pollTimer = setInterval(() => refreshStatus(url), 700);
}

async function refreshStatus(url) {
    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const data = await response.json();

    renderProcesses(data.jobs);
    renderSharedResource(data.test, data.jobs);
    renderCurrentMovement(data.logs);
    renderRaceCondition(data.test, data.jobs);
    renderResult(data.test);

    if (!['running', 'queued'].includes(data.test.status)) {
        clearInterval(pollTimer);
    }
}

function renderProcesses(jobs) {
    if (!jobs.length) {
        processGrid.innerHTML = '<div class="empty-state">Inicia una simulacion para ver los procesos en vivo.</div>';
        return;
    }

    processGrid.innerHTML = jobs.map((job) => {
        const state = processState(job.status, job.lock_status);
        const calculated = job.stock_read === null ? null : Math.max(0, Number(job.stock_read) - Number(currentQuantity()));

        return `
            <article class="process-card ${state.cardClass}">
                <div>
                    <div class="process-top">
                        <div>
                            <span class="process-title">Proceso ${job.order_number}</span>
                            <span class="process-meta">PID ${job.pid ?? '-'} - Pedido #${job.order_number}</span>
                        </div>
                        ${lockBadge(job)}
                    </div>
                    <div class="process-state">${state.label}</div>
                </div>
                <div class="process-values">
                    <div class="process-value"><span>Stock leido</span><strong>${job.stock_read ?? '-'}</strong></div>
                    <div class="process-value"><span>Stock calculado</span><strong>${job.stock_written ?? calculated ?? '-'}</strong></div>
                    <div class="process-value"><span>Stock escrito</span><strong>${job.status === 'finished' ? (job.stock_written ?? '-') : '-'}</strong></div>
                </div>
            </article>
        `;
    }).join('');
}

function renderSharedResource(test, jobs) {
    const owner = jobs.find((job) => ['lock_acquired', 'processing', 'updating'].includes(job.status));
    const waiting = jobs.filter((job) => job.status === 'waiting_lock');
    const currentStock = test.final_stock ?? latestVisibleStock(jobs) ?? test.initial_stock ?? '-';

    sharedProduct.textContent = test.product ?? '-';
    updateSharedStock(currentStock);

    if (owner?.pid) {
        resourceStatus.innerHTML = '<i class="state-dot state-busy"></i>En uso';
    } else if (waiting.length > 0) {
        resourceStatus.innerHTML = '<i class="state-dot state-waiting"></i>Hay procesos esperando';
    } else {
        setResourceFree();
    }

}

function renderCurrentMovement(logs) {
    const movement = findLastMovement(logs);
    if (movement && movement !== lastMovement) {
        lastMovement = movement;
        dataMovement.textContent = movement;
        dataMovement.classList.toggle('is-write', movement.includes('actualizo') || movement.includes('guardo'));
    }
}

function renderRaceCondition(test, jobs) {
    const groupedReads = jobs.reduce((groups, job) => {
        if (job.stock_read === null || job.stock_read === undefined) return groups;
        groups[job.stock_read] = groups[job.stock_read] || [];
        groups[job.stock_read].push(job);
        return groups;
    }, {});

    const raceGroup = Object.entries(groupedReads).find(([, group]) => group.length > 1);
    const finishedRace = test.status === 'finished' && test.method === 'none' && !test.is_consistent;

    if (!raceGroup && !finishedRace) {
        racePanel.hidden = true;
        return;
    }

    const [stock, group] = raceGroup ?? ['-', jobs];
    raceReaders.innerHTML = group.map((job) => `<span class="race-reader">PID ${job.pid ?? '-'} leyo: ${stock}</span>`).join('');
    racePanel.hidden = false;
}

function renderResult(test) {
    if (!['finished', 'finished_with_errors'].includes(test.status)) {
        resultPanel.hidden = true;
        return;
    }

    const consistent = test.is_consistent;
    setRunStatus(consistent ? 'Resultado correcto' : 'Condicion de carrera', consistent ? 'is-ok' : 'is-bad');
    resultPanel.hidden = false;
    resultPanel.className = `result-panel ${consistent ? 'is-ok' : 'is-bad'}`;
    resultPanel.innerHTML = `
        <h2>Simulacion completada</h2>
        <div class="result-grid">
            <div class="result-item"><span>Stock inicial</span><strong>${test.initial_stock}</strong></div>
            <div class="result-item"><span>Operaciones realizadas</span><strong>${test.order_count} pedidos x ${test.quantity_per_order}</strong></div>
            <div class="result-item"><span>Stock esperado</span><strong>${test.expected_stock}</strong></div>
            <div class="result-item"><span>Stock obtenido</span><strong>${test.final_stock}</strong></div>
            <div class="result-item"><span>Resultado</span><strong>${consistent ? 'Correcto: no se perdieron descuentos' : 'Error: se perdieron descuentos'}</strong></div>
        </div>
    `;
}

function processState(status, lockStatus) {
    if (status === 'queued') return { label: 'Esperando iniciar', cardClass: '' };
    if (status === 'reading') return { label: 'Iniciando pedido', cardClass: 'is-active' };
    if (status === 'waiting_lock') return { label: 'Esperando turno', cardClass: 'is-waiting' };
    if (status === 'lock_acquired') return { label: 'Tiene el turno', cardClass: 'is-active' };
    if (status === 'processing') return { label: lockStatus?.includes('optimista') ? 'Leyendo stock' : 'Calculando descuento', cardClass: 'is-active' };
    if (status === 'updating') return { label: 'Guardando stock', cardClass: 'is-active' };
    if (status === 'finished') return { label: 'Finalizado', cardClass: '' };
    if (status === 'failed') return { label: 'Error', cardClass: 'is-error' };
    return { label: status, cardClass: '' };
}

function lockBadge(job) {
    const text = job.lock_status ?? 'Pendiente';
    const normalized = text.toLowerCase();
    const className = normalized.includes('adquirido') || normalized.includes('atomica') ? 'is-ok'
        : (normalized.includes('esperando') || normalized.includes('conflicto') || normalized.includes('reintentando') ? 'is-waiting' : '');

    return `<span class="lock-badge ${className}">${escapeHtml(lockText(text))}</span>`;
}

function lockText(text) {
    const normalized = text.toLowerCase();
    if (normalized.includes('adquirido')) return 'Tiene el turno';
    if (normalized.includes('atomica')) return 'Guardo correctamente';
    if (normalized.includes('conflicto')) return 'Otro proceso cambio el stock';
    if (normalized.includes('reintentando')) return 'Vuelve a intentar';
    if (normalized.includes('optimista')) return 'Verificando stock';
    if (normalized.includes('esperando')) return 'Esperando turno';
    if (normalized.includes('sin lock')) return 'Sin control';
    if (normalized.includes('liberado')) return 'Turno liberado';
    return text;
}

function latestVisibleStock(jobs) {
    const finished = [...jobs].reverse().find((job) => job.status === 'finished' && job.stock_written !== null);
    return finished?.stock_written ?? null;
}

function updateSharedStock(value) {
    if (lastStock !== null && String(lastStock) !== String(value)) {
        sharedStock.classList.remove('stock-highlight');
        void sharedStock.offsetWidth;
        sharedStock.classList.add('stock-highlight');
    }

    sharedStock.textContent = value;
    lastStock = value;
}

function setResourceFree() {
    resourceStatus.innerHTML = '<i class="state-dot"></i>Disponible';
}

function setRunStatus(text, className) {
    runStatus.className = `status-pill ${className}`.trim();
    runStatus.textContent = text;
}

function findLastMovement(logs) {
    const movement = [...logs].reverse().find((log) => {
        const message = log.message.toLowerCase();
        return message.includes('leyo stock') || message.includes('actualizo') || message.includes('modifico') || message.includes('guardo') || message.includes('aplico');
    });

    return movement ? explainMovement(movement.message) : null;
}

function explainMovement(message) {
    const text = String(message);
    const pid = text.match(/PID (\d+)/)?.[1];
    const read = text.match(/leyo stock (\d+)/i);
    const write = text.match(/(?:modifico|actualizar|guardo|aplico).*?(\d+) -> (\d+)/i);

    if (read) return `${pid ? `PID ${pid} ` : ''}leyo stock ${read[1]}`;
    if (write) return `${pid ? `PID ${pid} ` : ''}actualizo stock ${write[1]} -> ${write[2]}`;
    if (text.toLowerCase().includes('esperando')) return `${pid ? `PID ${pid} ` : ''}espera su turno`;
    if (text.toLowerCase().includes('libero')) return `${pid ? `PID ${pid} ` : ''}termino y dejo pasar al siguiente`;
    if (text.toLowerCase().includes('conflicto')) return `${pid ? `PID ${pid} ` : ''}detecto que otro proceso cambio el stock`;

    return text.replace(/^PID \d+\s*/i, '').replace(/^[-: ]+/, '');
}

function currentQuantity() {
    return document.querySelector('#quantity_per_order').value || 0;
}

function showError(data) {
    if (data.errors) {
        errorBox.textContent = Object.values(data.errors).flat().join(' ');
        return;
    }

    errorBox.textContent = data.message || 'No se pudo iniciar la simulacion.';
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
