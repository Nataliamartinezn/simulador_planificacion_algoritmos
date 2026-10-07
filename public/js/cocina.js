document.addEventListener('DOMContentLoaded', function () {
    const lista = document.querySelector('[data-lista-preparaciones]');
    const botonAgregar = document.querySelector('[data-agregar-preparacion]');

    if (!lista || !botonAgregar) {
        return;
    }

    const actualizarNumeracion = function () {
        lista.querySelectorAll('[data-ticket]').forEach(function (ticket, indice) {
            const numero = ticket.querySelector('[data-numero-ticket]');

            if (numero) {
                numero.textContent = String(indice + 1);
            }
        });
    };

    const crearTicket = function () {
        const ticket = document.createElement('article');
        ticket.className = 'ticket';
        ticket.setAttribute('data-ticket', '');

        ticket.innerHTML = [
            '<div class="ticket-cabecera">',
            '<span>PREPARACION</span>',
            '<strong>#<span data-numero-ticket></span></strong>',
            '</div>',
            '<label>Nombre',
            '<input type="text" name="nombres[]" value="Nueva preparacion" required>',
            '</label>',
            '<label>Duracion en segundos',
            '<input type="number" name="duraciones[]" min="1" max="20" value="1" required>',
            '</label>',
            '<button class="boton boton-peligro" type="button" data-eliminar-preparacion>Eliminar</button>'
        ].join('');

        return ticket;
    };

    botonAgregar.addEventListener('click', function () {
        lista.appendChild(crearTicket());
        actualizarNumeracion();
    });

    lista.addEventListener('click', function (event) {
        const botonEliminar = event.target.closest('[data-eliminar-preparacion]');

        if (!botonEliminar) {
            return;
        }

        const tickets = lista.querySelectorAll('[data-ticket]');

        if (tickets.length === 1) {
            return;
        }

        botonEliminar.closest('[data-ticket]').remove();
        actualizarNumeracion();
    });

    actualizarNumeracion();
});
