<?php

/**
 * Helpers partagés entre la fiche de paie écran (employee-payslip.php)
 * et sa déclinaison PDF (employee-payslip-pdf.php).
 *
 * Les deux vues gardent des markups distincts (tables pour mPDF, divs pour
 * l'écran) mais partagent le formatage et la résolution du nom / rôle.
 *
 * Variables attendues dans la vue incluante : array $user, array $membership.
 * Définit : $empName (string), $role (string).
 */

if (!function_exists('payslip_hours')) {
    function payslip_hours(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('payslip_date')) {
    function payslip_date(string $date): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        return $dt ? $dt->format('d/m/Y') : $date;
    }
}

if (!function_exists('payslip_dow')) {
    function payslip_dow(string $date): string
    {
        $days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        return $dt ? $days[(int) $dt->format('w')] : '';
    }
}

$empName = trim(($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? ''))
    ?: ($user['display_name'] ?? ($user['email'] ?? ''));

$role = __(['admin' => 'role_owner', 'manager' => 'role_manager', 'staff' => 'role_employee'][$membership['role'] ?? 'staff'] ?? 'role_employee');
