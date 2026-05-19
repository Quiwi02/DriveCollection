// Toggle visibilidad contraseña
const toggle = document.getElementById('toggle-pass');
const inputPassword = document.getElementById('password');

if (toggle && inputPassword) {
    toggle.addEventListener('click', () => {
        const isPass = inputPassword.type === 'password';
        inputPassword.type = isPass ? 'text' : 'password';
        toggle.setAttribute('aria-label', isPass ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
}

// Validación básica cliente
const loginForm = document.getElementById('login-form');
if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
        const usuario = document.getElementById('usuario').value.trim();
        const password  = document.getElementById('password').value.trim();
        if (!usuario || !password) {
            e.preventDefault();
            alert('Por favor completa usuario y contraseña.');
        }
    });
}
