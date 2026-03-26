-- Seed: 05 — plannings de shifts (SQLite)
PRAGMA foreign_keys = ON;

INSERT INTO
    "shifts" (
        "store_id",
        "user_id",
        "shift_date",
        "start_time",
        "end_time",
        "shift_type_id",
        "cross_midnight",
        "pause_minutes",
        "duration_minutes",
        "starts_at",
        "ends_at",
        "created_by",
        "created_at",
        "updated_at"
    )
    -- ── MSHQ · semaine du 23 février ────────────────────────────────────────────
    -- Alice : Matin lundi → mercredi
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'alice.martin@kintai.local'
    ), '2026-02-23', '06:00', '14:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-02-23 06:00:00', '2026-02-23 14:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'alice.martin@kintai.local'
    ), '2026-02-24', '06:00', '14:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-02-24 06:00:00', '2026-02-24 14:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'alice.martin@kintai.local'
    ), '2026-02-25', '06:00', '14:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-02-25 06:00:00', '2026-02-25 14:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- Bob : Après-midi lundi → mercredi
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'bob.dupont@kintai.local'
    ), '2026-02-23', '14:00', '22:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-02-23 14:00:00', '2026-02-23 22:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'bob.dupont@kintai.local'
    ), '2026-02-24', '14:00', '22:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-02-24 14:00:00', '2026-02-24 22:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'bob.dupont@kintai.local'
    ), '2026-02-25', '14:00', '22:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-02-25 14:00:00', '2026-02-25 22:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- Chloé : Nuit lundi → mardi (cross midnight)
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'chloe.tanaka@kintai.local'
    ), '2026-02-23', '22:00', '06:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'NIGHT'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 1, 60, 420, '2026-02-23 22:00:00', '2026-02-24 06:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'chloe.tanaka@kintai.local'
    ), '2026-02-24', '22:00', '06:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'NIGHT'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 1, 60, 420, '2026-02-24 22:00:00', '2026-02-25 06:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- Yuki : Après-midi jeudi → vendredi
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'yuki.yamamoto@kintai.local'
    ), '2026-02-26', '14:00', '22:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-02-26 14:00:00', '2026-02-26 22:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'yuki.yamamoto@kintai.local'
    ), '2026-02-27', '14:00', '22:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-02-27 14:00:00', '2026-02-27 22:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- ── MSPARIS · semaine du 23 février ─────────────────────────────────────────
    -- David : Matin lundi, mercredi
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTPARIS'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'david.leblanc@kintai.local'
    ), '2026-02-23', '07:00', '15:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTPARIS'
            )
    ), 0, 60, 420, '2026-02-23 07:00:00', '2026-02-23 15:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTPARIS'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'david.leblanc@kintai.local'
    ), '2026-02-25', '07:00', '15:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTPARIS'
            )
    ), 0, 60, 420, '2026-02-25 07:00:00', '2026-02-25 15:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- Emma : Après-midi mardi, jeudi
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTPARIS'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'emma.sato@kintai.local'
    ), '2026-02-24', '15:00', '23:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTPARIS'
            )
    ), 0, 60, 420, '2026-02-24 15:00:00', '2026-02-24 23:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTPARIS'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'emma.sato@kintai.local'
    ), '2026-02-26', '15:00', '23:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTPARIS'
            )
    ), 0, 60, 420, '2026-02-26 15:00:00', '2026-02-26 23:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- ── MSHQ · semaine du 2 mars ─────────────────────────────────────────────────
    -- Alice : Matin lundi → mardi
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'alice.martin@kintai.local'
    ), '2026-03-02', '06:00', '14:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-03-02 06:00:00', '2026-03-02 14:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'alice.martin@kintai.local'
    ), '2026-03-03', '06:00', '14:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-03-03 06:00:00', '2026-03-03 14:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- Bob : Après-midi lundi → mardi
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'bob.dupont@kintai.local'
    ), '2026-03-02', '14:00', '22:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-03-02 14:00:00', '2026-03-02 22:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'bob.dupont@kintai.local'
    ), '2026-03-03', '14:00', '22:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 0, 60, 420, '2026-03-03 14:00:00', '2026-03-03 22:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- Chloé : Nuit lundi (cross midnight)
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTHQ'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'chloe.tanaka@kintai.local'
    ), '2026-03-02', '22:00', '06:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'NIGHT'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTHQ'
            )
    ), 1, 60, 420, '2026-03-02 22:00:00', '2026-03-03 06:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- ── MSPARIS · semaine du 2 mars ──────────────────────────────────────────────
    -- David : Matin lundi, mardi, jeudi
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTPARIS'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'david.leblanc@kintai.local'
    ), '2026-03-02', '07:00', '15:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTPARIS'
            )
    ), 0, 60, 420, '2026-03-02 07:00:00', '2026-03-02 15:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTPARIS'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'david.leblanc@kintai.local'
    ), '2026-03-03', '07:00', '15:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTPARIS'
            )
    ), 0, 60, 420, '2026-03-03 07:00:00', '2026-03-03 15:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTPARIS'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'david.leblanc@kintai.local'
    ), '2026-03-05', '07:00', '15:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'MORNING'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTPARIS'
            )
    ), 0, 60, 420, '2026-03-05 07:00:00', '2026-03-05 15:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
    -- Emma : Après-midi mercredi, vendredi
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTPARIS'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'emma.sato@kintai.local'
    ), '2026-03-04', '15:00', '23:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTPARIS'
            )
    ), 0, 60, 420, '2026-03-04 15:00:00', '2026-03-04 23:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now')
UNION ALL
SELECT (
        SELECT id
        FROM stores
        WHERE
            code = 'KTPARIS'
    ), (
        SELECT id
        FROM users
        WHERE
            email = 'emma.sato@kintai.local'
    ), '2026-03-06', '15:00', '23:00', (
        SELECT id
        FROM shift_types
        WHERE
            code = 'AFTERNOON'
            AND store_id = (
                SELECT id
                FROM stores
                WHERE
                    code = 'KTPARIS'
            )
    ), 0, 60, 420, '2026-03-06 15:00:00', '2026-03-06 23:00:00', (
        SELECT id
        FROM users
        WHERE
            email = 'admin@kintai.local'
    ), datetime('now'), datetime('now');