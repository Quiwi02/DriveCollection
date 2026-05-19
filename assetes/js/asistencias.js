document.addEventListener('DOMContentLoaded', () => {
    // ── MODAL ELEMENTOS
    const attendanceModal = document.getElementById('attendance-modal');
    const modalTitle      = document.getElementById('modal-title');
    const attendanceForm  = document.getElementById('attendance-form');

    // Form inputs
    const inputAction    = document.getElementById('form-action');
    const inputId        = document.getElementById('form-id-asistencia');
    const selectAdvisor  = document.getElementById('form-asesor');
    const inputFecha     = document.getElementById('form-fecha');
    const inputEntrada   = document.getElementById('form-hora-entrada');
    const inputSalida    = document.getElementById('form-hora-salida');
    const selectEstado   = document.getElementById('form-estado');
    const inputObs       = document.getElementById('form-observaciones');

    // Helper to get local date and time strings
    const getLocalInfo = () => {
        const d = new Date();
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');

        return {
            date: `${year}-${month}-${day}`,
            time: `${hours}:${minutes}`
        };
    };

    // ── ABRIR MODAL CREAR
    const btnCrear = document.getElementById('btn-registrar-asistencia');
    if (btnCrear) {
        btnCrear.addEventListener('click', () => {
            modalTitle.textContent = 'Registrar Asistencia';
            inputAction.value = 'crear';
            inputId.value = '';
            attendanceForm.reset();

            // Habilitar selección de asesor
            selectAdvisor.removeAttribute('disabled');
            
            // Pre-llenar con fecha y hora actual local
            const local = getLocalInfo();
            inputFecha.value = local.date;
            inputEntrada.value = local.time;
            inputSalida.value = '';

            selectEstado.value = 'presente';
            inputObs.value = '';

            attendanceModal.classList.add('open');
        });
    }

    // ── ABRIR MODAL EDITAR
    document.querySelectorAll('.btn-edit-attendance').forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.textContent = 'Editar Registro de Asistencia';
            inputAction.value = 'editar';

            inputId.value       = btn.dataset.id;
            selectAdvisor.value = btn.dataset.idUsuario;
            inputFecha.value    = btn.dataset.fecha;
            inputEntrada.value  = btn.dataset.horaEntrada;
            inputSalida.value   = btn.dataset.horaSalida || '';
            selectEstado.value  = btn.dataset.estado;
            inputObs.value      = btn.dataset.observaciones || '';

            attendanceModal.classList.add('open');
        });
    });

    // ── PREVENIR BLOQUEO DE ENVÍO
    attendanceForm.addEventListener('submit', () => {
        selectAdvisor.removeAttribute('disabled');
    });

    // ── CERRAR MODALES
    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            attendanceModal.classList.remove('open');
        });
    });

    window.addEventListener('click', (e) => {
        if (e.target === attendanceModal) attendanceModal.classList.remove('open');
    });
});
