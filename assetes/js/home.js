// ── Navbar scroll effect
window.addEventListener('scroll', () => {
    const navbar = document.getElementById('navbar');
    if (navbar) {
        navbar.classList.toggle('scrolled', window.scrollY > 50);
    }
});

// ── Modal
const overlay  = document.getElementById('modal-overlay');
const imgPpal  = document.getElementById('galeria-img-principal');
const thumbsCt = document.getElementById('galeria-thumbs');
const inputId  = document.getElementById('input-id-vehiculo');

function abrirModal(id, nombre, imgPrincipal) {
    if (!overlay) return;
    document.getElementById('modal-titulo').textContent = nombre;
    if (inputId) inputId.value = id;

    // Imagen principal
    if (imgPrincipal && imgPpal) {
        imgPpal.src = '/EntreVentaCarros/' + imgPrincipal;
        imgPpal.style.display = 'block';
    } else if (imgPpal) {
        imgPpal.src = '';
        imgPpal.style.display = 'none';
    }

    if (thumbsCt) thumbsCt.innerHTML = '';
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function cerrarModal() {
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
}

const modalClose = document.getElementById('modal-close');
if (modalClose) {
    modalClose.addEventListener('click', cerrarModal);
}
if (overlay) {
    overlay.addEventListener('click', (e) => { if (e.target === overlay) cerrarModal(); });
}

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarModal(); });
