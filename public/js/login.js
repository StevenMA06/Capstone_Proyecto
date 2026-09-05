const password = document.getElementById('password');
const toggle = document.getElementById('toggle-password');
if (password && toggle) {
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(show));
        toggle.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
}
