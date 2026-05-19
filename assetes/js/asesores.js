document.addEventListener('DOMContentLoaded', () => {
    // ── MODAL ELEMENTOS
    const advisorModal = document.getElementById('advisor-modal');
    const modalTitle   = document.getElementById('modal-title');
    const advisorForm  = document.getElementById('advisor-form');

    // Form inputs
    const inputAction    = document.getElementById('form-action');
    const inputId        = document.getElementById('form-id-usuario');
    const inputNombre    = document.getElementById('form-nombre');
    const inputCorreo    = document.getElementById('form-correo');
    const inputClave      = document.getElementById('form-clave');
    const labelClave     = document.getElementById('label-clave');
    const inputRol       = document.getElementById('form-rol');
    const inputActivo    = document.getElementById('form-activo');
    
    // Avatar upload preview elements
    const fileInput     = document.getElementById('form-avatar-input');
    const avatarPreview = document.getElementById('avatar-preview-img');
    const avatarPlaceholder = document.getElementById('avatar-preview-placeholder');

    // ── ABRIR MODAL CREAR
    const btnCrear = document.getElementById('btn-crear-asesor');
    if (btnCrear) {
        btnCrear.addEventListener('click', () => {
            modalTitle.textContent = 'Nuevo Asesor / Usuario';
            inputAction.value = 'crear';
            inputId.value = '';
            advisorForm.reset();
            
            // Requerir contraseña para nuevo usuario
            inputClave.setAttribute('required', 'required');
            labelClave.textContent = 'Clave (Sin hashear) *';
            
            // Reset preview
            avatarPreview.style.display = 'none';
            avatarPlaceholder.style.display = 'flex';
            
            // Valores por defecto
            inputRol.value = 'asesor';
            inputActivo.value = '1';
            
            advisorModal.classList.add('open');
        });
    }

    // ── ABRIR MODAL EDITAR
    document.querySelectorAll('.btn-edit-advisor').forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.textContent = 'Editar Asesor / Usuario';
            inputAction.value = 'editar';
            
            inputId.value     = btn.dataset.id;
            inputNombre.value = btn.dataset.nombre;
            inputCorreo.value = btn.dataset.correo;
            inputRol.value    = btn.dataset.rol;
            inputActivo.value = btn.dataset.activo;

            // Clave no obligatoria en edición
            inputClave.removeAttribute('required');
            labelClave.textContent = 'Clave (dejar en blanco para no cambiar)';
            inputClave.value = '';

            // Avatar preview
            const foto = btn.dataset.foto;
            if (foto) {
                avatarPreview.src = '/EntreVentaCarros/' + foto;
                avatarPreview.style.display = 'block';
                avatarPlaceholder.style.display = 'none';
            } else {
                avatarPreview.style.display = 'none';
                avatarPlaceholder.style.display = 'flex';
            }

            advisorModal.classList.add('open');
        });
    });

    // ── LECTURA DINÁMICA DE IMAGEN (PREVIEW)
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    avatarPreview.src = e.target.result;
                    avatarPreview.style.display = 'block';
                    avatarPlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // ── CERRAR MODALES
    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            advisorModal.classList.remove('open');
        });
    });

    window.addEventListener('click', (e) => {
        if (e.target === advisorModal) advisorModal.classList.remove('open');
    });
});
