document.addEventListener('DOMContentLoaded', () => {
    // ── 1. MODAL EDITAR LEAD
    const editModal = document.getElementById('edit-modal');
    const editForm  = document.getElementById('edit-form');
    const inputEditId = document.getElementById('edit-id-solicitud');
    const selectEditVeh = document.getElementById('edit-vehiculo');
    const inputEditNombre = document.getElementById('edit-nombre');
    const inputEditCorreo = document.getElementById('edit-correo');
    const inputEditTel = document.getElementById('edit-telefono');
    const selectEditAcq = document.getElementById('edit-metodo-adquisicion');
    const selectEditEst = document.getElementById('edit-estado');
    const inputEditObs = document.getElementById('edit-observaciones');

    document.querySelectorAll('.btn-edit-lead').forEach(btn => {
        btn.addEventListener('click', () => {
            inputEditId.value      = btn.dataset.id;
            selectEditVeh.value    = btn.dataset.idVehiculo;
            inputEditNombre.value  = btn.dataset.nombre;
            inputEditCorreo.value  = btn.dataset.correo;
            inputEditTel.value     = btn.dataset.telefono || '';
            selectEditAcq.value    = btn.dataset.metodoAdquisicion;
            selectEditEst.value    = btn.dataset.estado;
            inputEditObs.value      = btn.dataset.observaciones || '';

            editModal.classList.add('open');
        });
    });

    // ── 2. MODAL CONVERTIR LEAD A VENTA (ONBOARDING AUTOMATIZADO)
    const convertModal = document.getElementById('convert-modal');
    const convertForm  = document.getElementById('convert-form');
    
    // Convert Form inputs
    const inputConvLeadId  = document.getElementById('conv-id-solicitud');
    const selectConvVeh    = document.getElementById('conv-vehiculo');
    const inputConvDoc     = document.getElementById('conv-doc');
    const inputConvNombre  = document.getElementById('conv-nombre');
    const inputConvApell   = document.getElementById('conv-apellido');
    const inputConvCorreo  = document.getElementById('conv-correo');
    const inputConvTel     = document.getElementById('conv-telefono');
    const inputConvMonto   = document.getElementById('conv-monto');
    const selectConvMetodo = document.getElementById('conv-metodo-pago');
    const inputConvObs     = document.getElementById('conv-observaciones');

    document.querySelectorAll('.btn-convert-lead').forEach(btn => {
        btn.addEventListener('click', () => {
            convertForm.reset();

            // Guardar ID del lead para archivar tras la compra
            inputConvLeadId.value = btn.dataset.id;

            // Bloquear y seleccionar el vehículo cotizado
            selectConvVeh.value = btn.dataset.idVehiculo;
            
            // Auto-cargar precio del vehículo
            const selectedOption = selectConvVeh.options[selectConvVeh.selectedIndex];
            if (selectedOption) {
                const precio = selectedOption.dataset.precio || '0.00';
                inputConvMonto.value = parseFloat(precio).toFixed(2);
            }

            // Pre-llenar datos del cliente comprador desde el lead original
            const fullName = btn.dataset.nombre || '';
            const nameParts = fullName.split(' ');
            inputConvNombre.value = nameParts[0] || '';
            inputConvApell.value  = nameParts.slice(1).join(' ') || 'Pendiente'; // Si no hay apellido, prellena una marca temporal
            
            inputConvCorreo.value = btn.dataset.correo;
            inputConvTel.value    = btn.dataset.telefono || '';

            // Sincronizar método de adquisición a método de pago (si coincide)
            const acqMethod = btn.dataset.metodoAdquisicion;
            if (['Contado', 'Crédito', 'Leasing', 'Permuta'].includes(acqMethod)) {
                selectConvMetodo.value = acqMethod;
            } else {
                selectConvMetodo.value = 'Contado';
            }

            inputConvObs.value = `Conversión automatizada de Lead Web #${btn.dataset.id}.`;

            convertModal.classList.add('open');
        });
    });

    // ── CERRAR MODALES
    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            editModal.classList.remove('open');
            convertModal.classList.remove('open');
        });
    });

    window.addEventListener('click', (e) => {
        if (e.target === editModal) editModal.classList.remove('open');
        if (e.target === convertModal) convertModal.classList.remove('open');
    });
});
