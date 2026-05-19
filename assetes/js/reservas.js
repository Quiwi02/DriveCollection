document.addEventListener('DOMContentLoaded', () => {
    // ── MODAL ELEMENTOS
    const reserveModal = document.getElementById('reserve-modal');
    const modalTitle   = document.getElementById('modal-title');
    const reserveForm  = document.getElementById('reserve-form');

    // Form inputs
    const inputAction   = document.getElementById('form-action');
    const inputId       = document.getElementById('form-id-reserva');
    const selectClient  = document.getElementById('form-cliente');
    const selectVehicle = document.getElementById('form-vehiculo');
    const selectEstado  = document.getElementById('form-estado');
    const inputObs      = document.getElementById('form-observaciones');

    // ── ABRIR MODAL CREAR
    const btnCrear = document.getElementById('btn-crear-reserva');
    if (btnCrear) {
        btnCrear.addEventListener('click', () => {
            modalTitle.textContent = 'Nueva Reserva';
            inputAction.value = 'crear';
            inputId.value = '';
            reserveForm.reset();
            
            // Habilitar selección de cliente y carro en alta
            selectClient.removeAttribute('disabled');
            selectVehicle.removeAttribute('disabled');
            
            // Valores por defecto
            selectEstado.value = 'activa';
            inputObs.value = '';
            
            reserveModal.classList.add('open');
        });
    }

    // ── ABRIR MODAL EDITAR
    document.querySelectorAll('.btn-edit-reservation').forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.textContent = 'Editar Reserva';
            inputAction.value = 'editar';
            
            inputId.value       = btn.dataset.id;
            selectClient.value  = btn.dataset.idCliente;
            selectVehicle.value = btn.dataset.idVehiculo;
            selectEstado.value  = btn.dataset.estado;
            inputObs.value      = btn.dataset.observaciones || '';

            reserveModal.classList.add('open');
        });
    });

    // ── PREVENT DISABLED SUBMIT VALUES
    reserveForm.addEventListener('submit', () => {
        selectClient.removeAttribute('disabled');
        selectVehicle.removeAttribute('disabled');
    });

    // ── CERRAR MODALES
    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            reserveModal.classList.remove('open');
        });
    });

    window.addEventListener('click', (e) => {
        if (e.target === reserveModal) reserveModal.classList.remove('open');
    });
});
