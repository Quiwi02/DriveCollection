// Reloj en vivo
function updateDate() {
    const el = document.getElementById('live-date');
    if (!el) return;
    const now = new Date();
    el.textContent = now.toLocaleDateString('es-CO', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
}
updateDate();
setInterval(updateDate, 60000);
