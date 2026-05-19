document.addEventListener('DOMContentLoaded', () => {
    // ── MODAL ELEMENTOS
    const clientModal = document.getElementById('client-modal');
    const modalTitle  = document.getElementById('modal-title');
    const clientForm  = document.getElementById('client-form');

    // Form inputs
    const inputAction    = document.getElementById('form-action');
    const inputId        = document.getElementById('form-id-cliente');
    const inputNombre    = document.getElementById('form-nombre');
    const inputApellido  = document.getElementById('form-apellido');
    const inputTipoDoc   = document.getElementById('form-tipo-doc');
    const inputDocumento = document.getElementById('form-documento');
    const inputCorreo    = document.getElementById('form-correo');
    const inputTelefono  = document.getElementById('form-telefono');
    const inputDireccion = document.getElementById('form-direccion');

    // ── ABRIR MODAL CREAR
    const btnCrear = document.getElementById('btn-crear-cliente');
    if (btnCrear) {
        btnCrear.addEventListener('click', () => {
            modalTitle.textContent = 'Nuevo Cliente';
            inputAction.value = 'crear';
            inputId.value = '';
            clientForm.reset();
            
            // Valores por defecto
            inputTipoDoc.value = 'DNI';
            
            clientModal.classList.add('open');
        });
    }

    // ── ABRIR MODAL EDITAR
    document.querySelectorAll('.btn-edit-client').forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.textContent = 'Editar Cliente';
            inputAction.value = 'editar';
            
            inputId.value        = btn.dataset.id;
            inputNombre.value    = btn.dataset.nombre;
            inputApellido.value  = btn.dataset.apellido;
            inputTipoDoc.value   = btn.dataset.tipoDoc;
            inputDocumento.value = btn.dataset.documento;
            inputCorreo.value    = btn.dataset.correo;
            inputTelefono.value  = btn.dataset.telefono || '';
            inputDireccion.value = btn.dataset.direccion || '';

            clientModal.classList.add('open');
        });
    });

    // ── CERRAR MODALES
    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            clientModal.classList.remove('open');
        });
    });

    window.addEventListener('click', (e) => {
        if (e.target === clientModal) clientModal.classList.remove('open');
    });
});
