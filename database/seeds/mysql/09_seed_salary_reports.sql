-- Seed: 09 — rapports de salaire (MySQL)

INSERT IGNORE INTO `salary_reports`
    (`store_id`, `target_month`, `store_name`, `person_in_charge`,
     `total_payment`, `total_deductions`, `income_tax_base`,
     `withholding_tax`, `residence_tax`, `other_deductions`,
     `net_payment`, `active_employees`, `hand_delivered_salary`,
     `staff_man_hours`, `staff_total_payment`, `staff_avg_hourly_wage`,
     `employee_work_hours`, `new_hires`, `resigned_staff`,
     `hire_registrations`, `remarks`, `created_by`, `created_at`, `updated_at`)
SELECT 1, DATE_FORMAT(CURDATE(), '%Y-%m'), s.name, 'Admin',
       500000.00, 80000.00, 420000.00,
       25000.00, 15000.00, 10000.00,
       370000.00, 5, 0.00,
       520.00, 500000.00, 961.54,
       '{}', 1, 0,
       'Nouvel employé inscrit', NULL, 1, NOW(), NOW()
FROM `stores` s
WHERE s.id = 1;
