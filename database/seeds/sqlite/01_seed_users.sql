-- Seed: 01 — utilisateurs (SQLite)
-- Admin  : Admin1234!  → $2a$12$yhu0EP9VT14Oy6BzPsHbIOZYIKeztq9PLn9YnMAzd.fdlaT1MURrC
-- Staff  : Staff1234!  → $2a$12$P8e2BqvyYYA.AB/EwMruVu6mx3SFjWJN2byqy4HVmGnyrRnh6FlLW

PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO "users"
    ("email", "password_hash", "first_name", "last_name", "display_name",
     "color", "is_admin", "is_active", "created_at", "updated_at")
VALUES
    ('admin@kintai.local',
     '$2a$12$yhu0EP9VT14Oy6BzPsHbIOZYIKeztq9PLn9YnMAzd.fdlaT1MURrC',
     'Super', 'Admin', 'Super Admin', '#6366F1', 1, 1, datetime('now'), datetime('now')),

    ('alice.martin@kintai.local',
     '$2a$12$P8e2BqvyYYA.AB/EwMruVu6mx3SFjWJN2byqy4HVmGnyrRnh6FlLW',
     'Alice', 'Martin', 'Alice Martin', '#10B981', 0, 1, datetime('now'), datetime('now')),

    ('bob.dupont@kintai.local',
     '$2a$12$P8e2BqvyYYA.AB/EwMruVu6mx3SFjWJN2byqy4HVmGnyrRnh6FlLW',
     'Bob', 'Dupont', 'Bob Dupont', '#F59E0B', 0, 1, datetime('now'), datetime('now')),

    ('chloe.tanaka@kintai.local',
     '$2a$12$P8e2BqvyYYA.AB/EwMruVu6mx3SFjWJN2byqy4HVmGnyrRnh6FlLW',
     'Chloé', 'Tanaka', 'Chloé Tanaka', '#EC4899', 0, 1, datetime('now'), datetime('now')),

    ('yuki.yamamoto@kintai.local',
     '$2a$12$P8e2BqvyYYA.AB/EwMruVu6mx3SFjWJN2byqy4HVmGnyrRnh6FlLW',
     'Yuki', 'Yamamoto', 'Yuki Yamamoto', '#8B5CF6', 0, 1, datetime('now'), datetime('now')),

    ('david.leblanc@kintai.local',
     '$2a$12$P8e2BqvyYYA.AB/EwMruVu6mx3SFjWJN2byqy4HVmGnyrRnh6FlLW',
     'David', 'Leblanc', 'David Leblanc', '#0EA5E9', 0, 1, datetime('now'), datetime('now')),

    ('emma.sato@kintai.local',
     '$2a$12$P8e2BqvyYYA.AB/EwMruVu6mx3SFjWJN2byqy4HVmGnyrRnh6FlLW',
     'Emma', 'Sato', 'Emma Sato', '#EF4444', 0, 1, datetime('now'), datetime('now'));
