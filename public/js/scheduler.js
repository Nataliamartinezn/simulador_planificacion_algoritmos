const form = document.getElementById('scheduler-form');
const processBody = document.getElementById('process-body');
const errorBox = document.getElementById('error-box');
const simulationStatus = document.getElementById('simulation-status');
const simulationStatusText = document.getElementById('simulation-status-text');
const resultStatus = document.getElementById('result-status');
const algorithmPickers = document.querySelectorAll('input[name="algorithm"]');
let rowCounter = 0;

const descriptions = {
    fcfs: {
        name: 'First Come, First Served / First In, First Out',
        detail: 'Ejecuta los procesos segun su orden de llegada. Es no expropiativo: cuando un proceso toma la CPU, la conserva hasta finalizar su rafaga.',
    },
    sjf: {
        name: 'Shortest Job First',
        detail: 'Entre los procesos que ya llegaron, selecciona el que tiene la rafaga de CPU mas corta. Esta implementacion es no expropiativa.',
    },
    rr: {
        name: 'Round Robin',
        detail: 'Asigna la CPU por turnos usando un quantum configurable. Si un proceso no termina dentro de su turno, vuelve al final de la cola.',
    },
    priority: {
        name: 'Planificacion por Prioridad',
        detail: 'Selecciona el proceso listo con mayor prioridad segun el criterio elegido. Se puede definir si el numero menor o mayor representa mayor prioridad.',
    },
    mlfq: {
        name: 'Multilevel Feedback Queue',
        detail: 'Organiza los procesos en varias colas con distintos quantums. Los procesos nuevos entran en Q1, pueden bajar de nivel al consumir su quantum y pueden volver a Q1 con priority boost.',
    },
};

function addProcess(data = {}) {
    const index = rowCounter++;
    const id = data.id ?? `P${index + 1}`;
    const arrival = data.arrival ?? 0;
    const burst = data.burst ?? 1;
    const priority = data.priority ?? 1;

    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input name="processes[${index}][id]" value="${escapeHtml(id)}" required maxlength="20"></td>
        <td><input type="number" name="processes[${index}][arrival]" value="${arrival}" min="0" required></td>
        <td><input type="number" name="processes[${index}][burst]" value="${burst}" min="1" required></td>
        <td class="priority-cell"><input class="priority-input" type="number" name="processes[${index}][priority]" value="${priority}" required></td>
        <td>
            <button type="button" class="icon-action remove-process" aria-label="Eliminar proceso" title="Eliminar proceso">
                <span class="material-symbols-outlined" aria-hidden="true">delete</span>
            </button>
        </td>
    `;
    tr.querySelector('.remove-process').addEventListener('click', () => tr.remove());
    processBody.appendChild(tr);
    updatePriorityInputs();
}

function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;',
    }[character]));
}

function resetExample() {
    processBody.innerHTML = '';
    rowCounter = 0;
    [
        { id: 'P1', arrival: 0, burst: 8, priority: 2 },
        { id: 'P2', arrival: 1, burst: 4, priority: 1 },
        { id: 'P3', arrival: 2, burst: 9, priority: 3 },
        { id: 'P4', arrival: 3, burst: 5, priority: 2 },
        { id: 'P5', arrival: 5, burst: 2, priority: 1 },
    ].forEach(addProcess);
}

function updateAlgorithmOptions() {
    const value = document.querySelector('input[name="algorithm"]:checked').value;
    document.getElementById('rr-options').classList.toggle('hidden', value !== 'rr');
    document.getElementById('priority-options').classList.toggle('hidden', value !== 'priority');
    document.getElementById('mlfq-options').classList.toggle('hidden', value !== 'mlfq');
    const description = descriptions[value];
    document.getElementById('algorithm-info').innerHTML = `
        <strong>${description.name}</strong>
        <span>${description.detail}</span>
    `;
    updatePriorityInputs();
}

function updatePriorityInputs() {
    const selectedAlgorithm = document.querySelector('input[name="algorithm"]:checked')?.value;
    const enabled = selectedAlgorithm === 'priority';

    document.querySelectorAll('.priority-column, .priority-cell, .priority-result-column, .priority-result-cell').forEach(element => {
        element.classList.toggle('hidden', !enabled);
    });

    document.querySelectorAll('.priority-input').forEach(input => {
        input.disabled = !enabled;
        input.title = enabled ? 'Prioridad usada por el algoritmo seleccionado' : 'Este algoritmo no usa prioridad';
        input.closest('.priority-cell')?.classList.toggle('is-disabled-cell', !enabled);
    });
}

function buildFormData() {
    const disabledPriorityInputs = Array.from(document.querySelectorAll('.priority-input:disabled'));

    disabledPriorityInputs.forEach(input => {
        input.disabled = false;
    });

    const data = new FormData(form);

    disabledPriorityInputs.forEach(input => {
        input.disabled = true;
    });

    return data;
}

function colorForProcess(id) {
    if (id === 'IDLE') return '#898195';

    const palette = ['#1f5fbf', '#5b7cfa', '#067647', '#b54708', '#184c99', '#25324a'];
    const processNumber = Number(String(id).match(/\d+/)?.[0]);

    if (Number.isInteger(processNumber) && processNumber > 0) {
        return palette[(processNumber - 1) % palette.length];
    }

    let hash = 0;
    for (let i = 0; i < id.length; i++) hash = id.charCodeAt(i) + ((hash << 5) - hash);

    return palette[Math.abs(hash) % palette.length];
}

function ganttUnitWidth(total) {
    if (total <= 20) return 48;
    if (total <= 50) return 34;
    return 24;
}

function renderResults(data) {
    document.getElementById('results').classList.remove('hidden');
    setSimulationStatus('passed');
    document.getElementById('result-title').textContent = data.algorithm;

    let params = '';
    if (data.parameters?.quantum) params = `Quantum: ${data.parameters.quantum}`;
    if (data.parameters?.quantums) {
        params = `Quantum por cola: ${data.parameters.quantums.join(', ')} | Priority boost: ${data.parameters.boost_interval === 0 ? 'desactivado' : data.parameters.boost_interval}`;
    }
    document.getElementById('result-parameters').textContent = params;

    const metrics = [
        ['Espera promedio', data.averages.waiting],
        ['Retorno promedio', data.averages.turnaround],
        ['Respuesta promedio', data.averages.response],
        ['Tiempo final', data.summary.finish_time],
        ['Uso de CPU', `${data.summary.cpu_utilization}%`],
        ['Throughput', data.summary.throughput],
    ];
    document.getElementById('metrics').innerHTML = metrics.map(([label, value]) =>
        `<div class="metric"><span>${label}</span><strong>${value}</strong></div>`
    ).join('');

    const gantt = document.getElementById('gantt');
    const ganttScroll = gantt.closest('.gantt-scroll');
    const total = Math.max(data.summary.finish_time, 1);
    const unitWidth = ganttUnitWidth(total);
    let currentOffset = 0;
    const ticks = [];

    gantt.innerHTML = '';
    gantt.style.width = '';
    ganttScroll.querySelector('.gantt-axis')?.remove();
    ganttScroll.querySelector('.gantt-help')?.remove();

    const help = document.createElement('p');
    help.className = 'gantt-help';
    ganttScroll.prepend(help);

    data.timeline.forEach((segment, i) => {
        const width = Math.max(segment.duration * unitWidth, 68);
        const el = document.createElement('div');
        el.className = `gantt-segment${segment.process === 'IDLE' ? ' idle' : ''}`;
        el.style.background = colorForProcess(segment.process);
        el.style.width = `${width}px`;
        el.title = `${segment.process}: ${segment.start} a ${segment.end} (${segment.duration} u.t.)`;
        const queue = segment.queue ? `<span class="gantt-queue">Q${segment.queue}</span>` : '';
        el.innerHTML = `
            <strong>${escapeHtml(segment.process)}</strong>
            ${queue}
            <span class="gantt-range">${segment.start} - ${segment.end}</span>
            <span class="gantt-duration">${segment.duration} u.t.</span>
        `;
        gantt.appendChild(el);

        ticks.push({ value: segment.start, left: currentOffset });
        currentOffset += width;
        if (i === data.timeline.length - 1) ticks.push({ value: segment.end, left: currentOffset });
    });
    gantt.style.width = `${currentOffset}px`;

    const axis = document.createElement('div');
    axis.className = 'gantt-axis';
    axis.style.width = `${currentOffset}px`;
    axis.innerHTML = ticks.map(tick => `<span style="left: ${tick.left}px">${tick.value}</span>`).join('');
    gantt.after(axis);

    document.getElementById('result-body').innerHTML = data.processes.map(process => `
        <tr>
            <td><strong>${escapeHtml(process.id)}</strong></td>
            <td>${process.arrival}</td>
            <td>${process.burst}</td>
            <td class="priority-result-cell">${process.priority}</td>
            <td>${process.first_start}</td>
            <td>${process.completion}</td>
            <td>${process.turnaround}</td>
            <td>${process.waiting}</td>
            <td>${process.response}</td>
        </tr>
    `).join('');

    updatePriorityInputs();

    document.getElementById('results').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function showError(message) {
    errorBox.textContent = message;
    errorBox.classList.remove('hidden');
    setSimulationStatus('failed');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function setSimulationStatus(status) {
    const labels = {
        ready: 'Listo',
        running: 'Simulando',
        passed: 'Completado',
        failed: 'Error',
    };

    simulationStatus.className = `status-badge is-${status}`;
    simulationStatusText.textContent = labels[status];

    if (resultStatus) {
        resultStatus.className = `result-badge is-${status}`;
        resultStatus.textContent = labels[status].toLowerCase();
    }
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    errorBox.classList.add('hidden');

    if (processBody.children.length === 0) {
        showError('Agrega al menos un proceso antes de simular.');
        return;
    }

    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    submit.textContent = 'Simulando...';
    setSimulationStatus('running');

    try {
        const response = await fetch(form.dataset.simulateUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
            },
            body: buildFormData(),
        });

        const data = await response.json();
        if (!response.ok) {
            if (data.errors) {
                showError(Object.values(data.errors).flat().join('\n'));
            } else {
                showError(data.message ?? 'No se pudo realizar la simulacion.');
            }
            return;
        }

        renderResults(data);
    } catch (error) {
        showError('Error de comunicacion con el servidor. Revisa que Laravel este ejecutandose.');
    } finally {
        submit.disabled = false;
        submit.textContent = 'Simular';
    }
});

document.getElementById('add-process').addEventListener('click', () => addProcess({ burst: 1, priority: 1 }));
document.getElementById('clear-processes').addEventListener('click', () => {
    processBody.innerHTML = '';
});
algorithmPickers.forEach(picker => {
    picker.addEventListener('change', updateAlgorithmOptions);
});

resetExample();
updateAlgorithmOptions();
