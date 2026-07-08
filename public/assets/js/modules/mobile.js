/**
 * Layout mobile : ouverture/fermeture du drawer de navigation.
 * Fonctions globales appelées via onclick dans layout/mobile.php.
 */
function mobDrawerOpen() {
    var drawer  = document.getElementById('mobDrawer');
    var overlay = document.getElementById('mobDrawerOverlay');
    if (drawer)  drawer.classList.add('open');
    if (overlay) overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function mobDrawerClose() {
    var drawer  = document.getElementById('mobDrawer');
    var overlay = document.getElementById('mobDrawerOverlay');
    if (drawer)  drawer.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') mobDrawerClose();
});
