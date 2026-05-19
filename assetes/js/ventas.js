document.addEventListener('DOMContentLoaded', () => {
    // ── MODAL ELEMENTOS
    const saleModal = document.getElementById('sale-modal');
    const modalTitle = document.getElementById('modal-title');
    const saleForm  = document.getElementById('sale-form');

    // Form inputs
    const inputAction    = document.getElementById('form-action');
    const inputId        = document.getElementById('form-id-venta');
    const selectClient   = document.getElementById('form-cliente');
    const selectVehicle  = document.getElementById('form-vehiculo');
    const inputMonto     = document.getElementById('form-monto');
    const selectMetodo   = document.getElementById('form-metodo-pago');
    const inputObs       = document.getElementById('form-observaciones');

    // ── AUTOMATIZACIÓN DE PRECIO DE LISTA
    if (selectVehicle) {
        selectVehicle.addEventListener('change', () => {
            const selectedOption = selectVehicle.options[selectVehicle.selectedIndex];
            if (selectedOption) {
                const precio = selectedOption.dataset.precio || '0.00';
                inputMonto.value = parseFloat(precio).toFixed(2);
            }
        });
    }

    // ── ABRIR MODAL CREAR
    const btnCrear = document.getElementById('btn-registrar-venta');
    if (btnCrear) {
        btnCrear.addEventListener('click', () => {
            modalTitle.textContent = 'Registrar Venta';
            inputAction.value = 'crear';
            inputId.value = '';
            saleForm.reset();
            
            // Habilitar selects
            selectClient.removeAttribute('disabled');
            selectVehicle.removeAttribute('disabled');
            
            inputMonto.value = '0.00';
            selectMetodo.value = 'Contado';
            
            saleModal.classList.add('open');
        });
    }

    // ── ABRIR MODAL EDITAR
    document.querySelectorAll('.btn-edit-sale').forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.textContent = 'Editar Registro de Venta';
            inputAction.value = 'editar';
            
            inputId.value      = btn.dataset.id;
            selectClient.value = btn.dataset.idCliente;
            
            // Set vehicle selection
            selectVehicle.value = btn.dataset.idVehiculo;
            
            // Set payment details
            inputMonto.value    = parseFloat(btn.dataset.monto).toFixed(2);
            selectMetodo.value  = btn.dataset.metodoPago;
            inputObs.value      = btn.dataset.observaciones || '';

            saleModal.classList.add('open');
        });
    });

    // ── PREVENIR BLOQUEO DE ENVÍO
    saleForm.addEventListener('submit', () => {
        selectClient.removeAttribute('disabled');
        selectVehicle.removeAttribute('disabled');
    });

    // ── CERRAR MODALES
    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            saleModal.classList.remove('open');
        });
    });

    window.addEventListener('click', (e) => {
        if (e.target === saleModal) saleModal.classList.remove('open');
    });
});
