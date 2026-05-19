document.addEventListener('DOMContentLoaded', () => {
    // ── MODAL ELEMENTOS
    const vehicleModal = document.getElementById('vehicle-modal');
    const imageModal = document.getElementById('image-modal');
    
    const modalTitle = document.getElementById('modal-title');
    const vehicleForm = document.getElementById('vehicle-form');
    
    // Inputs del formulario de vehículo
    const inputAction    = document.getElementById('form-action');
    const inputId        = document.getElementById('form-id-vehiculo');
    const inputMarca     = document.getElementById('form-marca');
    const inputModelo    = document.getElementById('form-modelo');
    const inputAnio      = document.getElementById('form-anio');
    const inputPrecio    = document.getElementById('form-precio');
    const inputColor     = document.getElementById('form-color');
    const inputKms       = document.getElementById('form-kilometraje');
    const inputTrans     = document.getElementById('form-transmision');
    const inputComb      = document.getElementById('form-combustible');
    const inputDesc      = document.getElementById('form-descripcion');
    const inputEstado    = document.getElementById('form-estado');

    // Inputs de modal imágenes
    const imageVehicleId = document.getElementById('image-vehicle-id');
    const imagesGrid     = document.getElementById('images-grid');

    // ── ABRIR MODAL CREAR
    const btnCrear = document.getElementById('btn-crear-vehiculo');
    if (btnCrear) {
        btnCrear.addEventListener('click', () => {
            modalTitle.textContent = 'Nuevo Vehículo';
            inputAction.value = 'crear';
            inputId.value = '';
            vehicleForm.reset();
            
            // Valores por defecto
            inputTrans.value = 'Automática';
            inputComb.value = 'Gasolina';
            inputEstado.value = 'disponible';
            
            vehicleModal.classList.add('open');
        });
    }

    // ── ABRIR MODAL EDITAR
    document.querySelectorAll('.btn-edit-vehicle').forEach(btn => {
        btn.addEventListener('click', () => {
            modalTitle.textContent = 'Editar Vehículo';
            inputAction.value = 'editar';
            
            inputId.value     = btn.dataset.id;
            inputMarca.value  = btn.dataset.marca;
            inputModelo.value = btn.dataset.modelo;
            inputAnio.value   = btn.dataset.anio;
            inputPrecio.value = btn.dataset.precio;
            inputColor.value  = btn.dataset.color || '';
            inputKms.value    = btn.dataset.kilometraje || 0;
            inputTrans.value  = btn.dataset.transmision;
            inputComb.value   = btn.dataset.combustible;
            inputDesc.value   = btn.dataset.descripcion || '';
            inputEstado.value = btn.dataset.estado;

            vehicleModal.classList.add('open');
        });
    });

    // ── ABRIR MODAL IMÁGENES
    document.querySelectorAll('.btn-images-vehicle').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            imageVehicleId.value = id;
            loadVehicleImages(id);
            imageModal.classList.add('open');
        });
    });

    // ── CARGAR IMÁGENES AJAX
    function loadVehicleImages(id) {
        imagesGrid.innerHTML = '<div style="color:var(--muted);grid-column:1/-1;text-align:center;padding:1.5rem;">Cargando imágenes...</div>';
        
        fetch('vehiculos.php?action=get_images&id_vehiculos=' + id)
            .then(res => res.json())
            .then(images => {
                imagesGrid.innerHTML = '';
                if (images.length === 0) {
                    imagesGrid.innerHTML = '<div style="color:var(--muted);grid-column:1/-1;text-align:center;padding:1.5rem;">Este vehículo no tiene imágenes cargadas aún.</div>';
                    return;
                }
                
                images.forEach(img => {
                    const card = document.createElement('div');
                    card.className = `image-thumb-card ${img.es_principal ? 'is-principal' : ''}`;
                    
                    let principalBadge = '';
                    let principalButton = '';
                    
                    if (img.es_principal) {
                        principalBadge = '<span class="badge-principal-pill">Principal</span>';
                    } else {
                        principalButton = `
                            <button type="button" class="btn-set-principal" data-id="${img.id_imagenes_vehiculo}" title="Establecer como principal" style="color:var(--accent-light)">
                                <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M12 2L1 21h22L12 2zm0 3.99L19.53 19H4.47L12 5.99zM13 16h-2v2h2v-2zm0-6h-2v4h2v-4z"/></svg>
                            </button>
                        `;
                    }
                    
                    card.innerHTML = `
                        ${principalBadge}
                        <img src="/EntreVentaCarros/${img.ruta}" class="image-thumb-preview" alt="Miniatura">
                        <div class="image-thumb-actions">
                            ${principalButton}
                            <button type="button" class="btn-delete-image" data-id="${img.id_imagenes_vehiculo}" title="Eliminar imagen" style="color:#ef4444;margin-left:auto;">
                                <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </div>
                    `;
                    
                    imagesGrid.appendChild(card);
                });
                
                // Bind set principal
                document.querySelectorAll('.btn-set-principal').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const imgId = btn.dataset.id;
                        window.location.href = `vehiculos.php?action=set_principal&id_imagenes_vehiculo=${imgId}&id_vehiculos=${id}`;
                    });
                });

                // Bind delete image
                document.querySelectorAll('.btn-delete-image').forEach(btn => {
                    btn.addEventListener('click', () => {
                        if (confirm('¿Estás seguro de que deseas eliminar esta imagen?')) {
                            const imgId = btn.dataset.id;
                            window.location.href = `vehiculos.php?action=delete_image&id_imagenes_vehiculo=${imgId}&id_vehiculos=${id}`;
                        }
                    });
                });
            })
            .catch(err => {
                imagesGrid.innerHTML = '<div style="color:#ef4444;grid-column:1/-1;text-align:center;padding:1.5rem;">Error al cargar las imágenes.</div>';
            });
    }

    // ── CERRAR MODALES
    document.querySelectorAll('.btn-close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            vehicleModal.classList.remove('open');
            imageModal.classList.remove('open');
        });
    });

    window.addEventListener('click', (e) => {
        if (e.target === vehicleModal) vehicleModal.classList.remove('open');
        if (e.target === imageModal) imageModal.classList.remove('open');
    });

    // Auto-trigger upload click on container
    const uploadTrigger = document.getElementById('upload-trigger');
    const uploadInput = document.getElementById('image-upload-input');
    if (uploadTrigger && uploadInput) {
        uploadTrigger.addEventListener('click', () => {
            uploadInput.click();
        });
        uploadInput.addEventListener('change', () => {
            if (uploadInput.files.length > 0) {
                document.getElementById('image-upload-form').submit();
            }
        });
    }
});
