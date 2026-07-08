/**
 * Dashboard admin : panneau de personnalisation des widgets.
 * Fonction globale appelée via onclick dans dashboard/index.php.
 */
function adminDashCustomize() {
    var p = document.getElementById('admin-dash-customize');
    if (!p) return;
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
