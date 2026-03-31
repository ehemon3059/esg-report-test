-- ============================================================================
-- ESG Reporting System - Database Schema
-- Database: esg_reporting_db
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================================
-- Create Database
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `esg_reporting_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `esg_reporting_db`;

-- ============================================================================
-- TABLE: companies
-- Core organization/company information
-- ============================================================================

CREATE TABLE IF NOT EXISTS `companies` (
    `id`                        VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `name`                      VARCHAR(255)    NOT NULL COMMENT 'Company name',
    `country_of_registration`   VARCHAR(100)    DEFAULT NULL COMMENT 'Country where company is registered',
    `industry`                  VARCHAR(100)    DEFAULT NULL COMMENT 'Industry sector',
    `registration_number`       VARCHAR(100)    DEFAULT NULL COMMENT 'Legal registration number',
    `website`                   VARCHAR(255)    DEFAULT NULL,
    `description`               TEXT            DEFAULT NULL,
    `created_at`                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`                TIMESTAMP       NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Organization/company information';

-- ============================================================================
-- TABLE: users
-- User authentication and account management
-- ============================================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id`            VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `company_id`    VARCHAR(36)     NOT NULL COMMENT 'FK to companies.id',
    `name`          VARCHAR(255)    NOT NULL COMMENT 'Full name',
    `email`         VARCHAR(255)    NOT NULL COMMENT 'Login email (unique)',
    `password`      VARCHAR(255)    NOT NULL COMMENT 'bcrypt hashed password',
    `role`          ENUM('admin','user','viewer') NOT NULL DEFAULT 'user' COMMENT 'User role',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    TIMESTAMP       NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_company_id` (`company_id`),
    CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User accounts and authentication';

-- ============================================================================
-- TABLE: sites
-- Facility/location data per company
-- ============================================================================

CREATE TABLE IF NOT EXISTS `sites` (
    `id`            VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `company_id`    VARCHAR(36)     NOT NULL COMMENT 'FK to companies.id',
    `name`          VARCHAR(255)    NOT NULL COMMENT 'Site/facility name',
    `address`       TEXT            DEFAULT NULL COMMENT 'Full address',
    `country`       VARCHAR(100)    DEFAULT NULL COMMENT 'Country code or name',
    `created_by`    VARCHAR(36)     NOT NULL COMMENT 'FK to users.id',
    `updated_by`    VARCHAR(36)     DEFAULT NULL COMMENT 'FK to users.id',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    TIMESTAMP       NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
    `deleted_by`    VARCHAR(36)     DEFAULT NULL COMMENT 'FK to users.id',
    PRIMARY KEY (`id`),
    KEY `idx_sites_company_id` (`company_id`),
    KEY `idx_sites_created_by` (`created_by`),
    CONSTRAINT `fk_sites_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sites_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_sites_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Facility and location data';

-- ============================================================================
-- TABLE: emission_factors
-- Reference library for CO2e emission conversion factors
-- ============================================================================

CREATE TABLE IF NOT EXISTS `emission_factors` (
    `id`            VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `activity_type` VARCHAR(100)    NOT NULL COMMENT 'Type of activity (e.g. diesel, electricity_grid_uk)',
    `scope`         ENUM('Scope 1','Scope 2 Location-Based','Scope 2 Market-Based','Scope 3') NOT NULL COMMENT 'GHG Protocol scope',
    `region`        VARCHAR(100)    DEFAULT NULL COMMENT 'Geographic region for the factor',
    `factor`        DECIMAL(18,8)   NOT NULL COMMENT 'kg CO2e per unit of activity',
    `unit`          VARCHAR(50)     NOT NULL COMMENT 'Unit of the activity (e.g. litre, kWh)',
    `source`        VARCHAR(255)    DEFAULT NULL COMMENT 'Data source (e.g. DEFRA 2024)',
    `version`       VARCHAR(50)     DEFAULT NULL COMMENT 'Version of the emission factor set',
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = inactive',
    `valid_from`    TIMESTAMP       NULL DEFAULT NULL COMMENT 'Start of validity period',
    `valid_until`   TIMESTAMP       NULL DEFAULT NULL COMMENT 'End of validity period (NULL = still valid)',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_emission_factors` (`activity_type`, `scope`, `region`, `version`),
    KEY `idx_ef_activity_scope` (`activity_type`, `scope`),
    KEY `idx_ef_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CO2e emission conversion factors reference library';

-- ============================================================================
-- TABLE: energy_activities
-- Energy consumption activity data (Scope 2)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `energy_activities` (
    `id`            VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `site_id`       VARCHAR(36)     NOT NULL COMMENT 'FK to sites.id',
    `date`          DATE            NOT NULL COMMENT 'Date of energy consumption',
    `energy_type`   VARCHAR(100)    NOT NULL COMMENT 'Type of energy (e.g. electricity, heat)',
    `consumption`   DECIMAL(15,4)   NOT NULL COMMENT 'Amount consumed',
    `unit`          VARCHAR(50)     NOT NULL COMMENT 'Unit of consumption (e.g. kWh)',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ea_site_id` (`site_id`),
    KEY `idx_ea_date` (`date`),
    CONSTRAINT `fk_ea_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Energy consumption activities (Scope 2)';

-- ============================================================================
-- TABLE: fuel_activities
-- Fuel consumption activity data (Scope 1)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `fuel_activities` (
    `id`            VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `site_id`       VARCHAR(36)     NOT NULL COMMENT 'FK to sites.id',
    `date`          DATE            NOT NULL COMMENT 'Date of fuel consumption',
    `fuel_type`     VARCHAR(100)    NOT NULL COMMENT 'Type of fuel (e.g. diesel, natural_gas)',
    `volume`        DECIMAL(15,4)   NOT NULL COMMENT 'Volume consumed',
    `unit`          VARCHAR(50)     NOT NULL COMMENT 'Unit of volume (e.g. litre, m3)',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fa_site_id` (`site_id`),
    KEY `idx_fa_date` (`date`),
    CONSTRAINT `fk_fa_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fuel consumption activities (Scope 1)';

-- ============================================================================
-- TABLE: emission_records
-- Calculated greenhouse gas emission results
-- ============================================================================

CREATE TABLE IF NOT EXISTS `emission_records` (
    `id`                    VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `company_id`            VARCHAR(36)     NOT NULL COMMENT 'FK to companies.id',
    `scope`                 ENUM('Scope 1','Scope 2 Location-Based','Scope 2 Market-Based','Scope 3') NOT NULL COMMENT 'GHG Protocol scope',
    `tco2e_calculated`      DECIMAL(18,6)   NOT NULL COMMENT 'Calculated emissions in tonnes CO2 equivalent',
    `energy_activity_id`    VARCHAR(36)     DEFAULT NULL COMMENT 'FK to energy_activities.id (if from energy)',
    `fuel_activity_id`      VARCHAR(36)     DEFAULT NULL COMMENT 'FK to fuel_activities.id (if from fuel)',
    `emission_factor_id`    VARCHAR(36)     NOT NULL COMMENT 'FK to emission_factors.id used for calculation',
    `date_calculated`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this record was calculated',
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_er_company_id` (`company_id`),
    KEY `idx_er_scope` (`scope`),
    KEY `idx_er_date_calculated` (`date_calculated`),
    KEY `idx_er_energy_activity_id` (`energy_activity_id`),
    KEY `idx_er_fuel_activity_id` (`fuel_activity_id`),
    CONSTRAINT `fk_er_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_er_energy_activity` FOREIGN KEY (`energy_activity_id`) REFERENCES `energy_activities` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_er_fuel_activity` FOREIGN KEY (`fuel_activity_id`) REFERENCES `fuel_activities` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_er_emission_factor` FOREIGN KEY (`emission_factor_id`) REFERENCES `emission_factors` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Calculated GHG emission records';

-- ============================================================================
-- TABLE: environmental_topics
-- ESRS E1-E5 environmental reporting data
-- ============================================================================

CREATE TABLE IF NOT EXISTS `environmental_topics` (
    `id`                            VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `company_id`                    VARCHAR(36)     NOT NULL COMMENT 'FK to companies.id',
    `reporting_period`              VARCHAR(7)      NOT NULL COMMENT 'Reporting period in YYYY-MM format',
    `status`                        ENUM('DRAFT','UNDER_REVIEW','APPROVED','PUBLISHED','REJECTED') NOT NULL DEFAULT 'DRAFT',

    -- ESRS E1: Climate Change
    `e1_material`                   TINYINT(1)      DEFAULT 0 COMMENT '1 = material topic, 0 = not material',
    `e1_climate_policy`             TEXT            DEFAULT NULL COMMENT 'Climate policy description',
    `e1_reduction_target`           TEXT            DEFAULT NULL COMMENT 'Emission reduction targets',

    -- ESRS E2: Pollution
    `e2_material`                   TINYINT(1)      DEFAULT 0,
    `e2_nox_t_per_year`             DECIMAL(15,4)   DEFAULT NULL COMMENT 'NOx emissions in tonnes per year',
    `e2_sox_t_per_year`             DECIMAL(15,4)   DEFAULT NULL COMMENT 'SOx emissions in tonnes per year',

    -- ESRS E3: Water and Marine Resources
    `e3_material`                   TINYINT(1)      DEFAULT 0,
    `e3_water_withdrawal_m3`        DECIMAL(15,4)   DEFAULT NULL COMMENT 'Total water withdrawal in m3',
    `e3_water_recycling_rate_pct`   INT             DEFAULT NULL COMMENT 'Water recycling rate 0-100%',

    -- ESRS E4: Biodiversity and Ecosystems
    `e4_material`                   TINYINT(1)      DEFAULT 0,
    `e4_protected_areas_impact`     TEXT            DEFAULT NULL COMMENT 'Impact on protected areas description',

    -- ESRS E5: Resource Use and Circular Economy
    `e5_material`                   TINYINT(1)      DEFAULT 0,
    `e5_recycling_rate_pct`                 INT     DEFAULT NULL COMMENT 'Overall recycling rate 0-100%',
    `e5_recycled_input_materials_pct`       INT     DEFAULT NULL COMMENT 'Recycled input materials rate 0-100%',

    `created_by`    VARCHAR(36)     NOT NULL COMMENT 'FK to users.id',
    `updated_by`    VARCHAR(36)     DEFAULT NULL COMMENT 'FK to users.id',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_env_company_period` (`company_id`, `reporting_period`),
    KEY `idx_env_company_id` (`company_id`),
    CONSTRAINT `fk_env_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_env_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_env_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ESRS E1-E5 environmental reporting data';

-- ============================================================================
-- TABLE: social_topics
-- ESRS S1-S4 social reporting data
-- ============================================================================

CREATE TABLE IF NOT EXISTS `social_topics` (
    `id`                                VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `company_id`                        VARCHAR(36)     NOT NULL COMMENT 'FK to companies.id',
    `reporting_period`                  VARCHAR(7)      NOT NULL COMMENT 'Reporting period in YYYY-MM format',
    `status`                            ENUM('DRAFT','UNDER_REVIEW','APPROVED','PUBLISHED','REJECTED') NOT NULL DEFAULT 'DRAFT',

    -- ESRS S1: Own Workforce
    `s1_material`                       TINYINT(1)      DEFAULT 0 COMMENT '1 = material topic',
    `s1_employee_count_by_contract`     TEXT            DEFAULT NULL COMMENT 'Employee breakdown by contract type',
    `s1_health_and_safety`              TEXT            DEFAULT NULL COMMENT 'Health and safety disclosures',
    `s1_training_hours_per_employee`    INT             DEFAULT NULL COMMENT 'Average training hours per employee',

    -- ESRS S2: Workers in the Value Chain
    `s2_material`                       TINYINT(1)      DEFAULT 0,
    `s2_pct_suppliers_audited`          INT             DEFAULT NULL COMMENT 'Percentage of suppliers audited 0-100',
    `s2_remediation_actions`            TEXT            DEFAULT NULL COMMENT 'Remediation actions taken',

    -- ESRS S3: Affected Communities
    `s3_material`                       TINYINT(1)      DEFAULT 0,
    `s3_community_engagement`           TEXT            DEFAULT NULL COMMENT 'Community engagement activities',
    `s3_complaints_and_outcomes`        TEXT            DEFAULT NULL COMMENT 'Community complaints and outcomes',

    -- ESRS S4: Consumers and End-users
    `s4_material`                       TINYINT(1)      DEFAULT 0,
    `s4_product_safety_incidents`       INT             DEFAULT NULL COMMENT 'Number of product safety incidents',
    `s4_consumer_remediation`           TEXT            DEFAULT NULL COMMENT 'Consumer remediation actions',

    `created_by`    VARCHAR(36)     NOT NULL COMMENT 'FK to users.id',
    `updated_by`    VARCHAR(36)     DEFAULT NULL COMMENT 'FK to users.id',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_social_company_period` (`company_id`, `reporting_period`),
    KEY `idx_social_company_id` (`company_id`),
    CONSTRAINT `fk_social_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_social_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_social_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ESRS S1-S4 social reporting data';

-- ============================================================================
-- TABLE: s_governance
-- ESRS G1 governance reporting data
-- ============================================================================

CREATE TABLE IF NOT EXISTS `s_governance` (
    `id`                                    VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `company_id`                            VARCHAR(36)     NOT NULL COMMENT 'FK to companies.id',
    `reporting_period`                      VARCHAR(7)      NOT NULL COMMENT 'Reporting period in YYYY-MM format',
    `status`                                ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',

    -- ESRS G1: Business Conduct
    `g1_board_composition_independence`     TEXT            DEFAULT NULL COMMENT 'Board independence and composition details',
    `g1_gender_diversity_pct`               INT             DEFAULT NULL COMMENT 'Board gender diversity percentage 0-100',
    `g1_esg_oversight`                      TEXT            DEFAULT NULL COMMENT 'ESG oversight mechanisms',
    `g1_whistleblower_cases`                TEXT            DEFAULT NULL COMMENT 'Whistleblower cases description',
    `g1_anti_corruption_policies`           TEXT            DEFAULT NULL COMMENT 'Anti-corruption and bribery policies',
    `g1_related_party_controls`             TEXT            DEFAULT NULL COMMENT 'Related party transaction controls',

    `created_by`    VARCHAR(36)     NOT NULL COMMENT 'FK to users.id',
    `updated_by`    VARCHAR(36)     DEFAULT NULL COMMENT 'FK to users.id',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_gov_company_period` (`company_id`, `reporting_period`),
    KEY `idx_gov_company_id` (`company_id`),
    CONSTRAINT `fk_gov_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gov_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_gov_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ESRS G1 governance reporting data';

-- ============================================================================
-- TABLE: eu_taxonomy
-- EU Taxonomy alignment reporting data
-- ============================================================================

CREATE TABLE IF NOT EXISTS `eu_taxonomy` (
    `id`                                VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `company_id`                        VARCHAR(36)     NOT NULL COMMENT 'FK to companies.id',
    `reporting_period`                  VARCHAR(7)      NOT NULL COMMENT 'Reporting period in YYYY-MM format',
    `status`                            ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',

    `economic_activities`               TEXT            DEFAULT NULL COMMENT 'Description of economic activities assessed',
    `technical_screening_criteria`      TEXT            DEFAULT NULL COMMENT 'Technical screening criteria applied',

    -- Revenue KPIs (%)
    `taxonomy_eligible_revenue_pct`     INT             DEFAULT NULL COMMENT 'Taxonomy-eligible revenue %',
    `taxonomy_aligned_revenue_pct`      INT             DEFAULT NULL COMMENT 'Taxonomy-aligned revenue %',

    -- CapEx KPIs (%)
    `taxonomy_eligible_capex_pct`       INT             DEFAULT NULL COMMENT 'Taxonomy-eligible CapEx %',
    `taxonomy_aligned_capex_pct`        INT             DEFAULT NULL COMMENT 'Taxonomy-aligned CapEx %',

    -- OpEx KPIs (%)
    `taxonomy_aligned_opex_pct`         INT             DEFAULT NULL COMMENT 'Taxonomy-aligned OpEx %',

    -- DNSH & Minimum Safeguards
    `dnsh_status`                       ENUM('ALL_OBJECTIVES_PASSED','SOME_OBJECTIVES_NOT_MET','ASSESSMENT_IN_PROGRESS') DEFAULT NULL COMMENT 'Do No Significant Harm assessment status',
    `social_safeguards_status`          ENUM('FULL_COMPLIANCE','NON_COMPLIANCE','PARTIAL_REMEDIATION') DEFAULT NULL COMMENT 'Minimum social safeguards status',

    `created_by`    VARCHAR(36)     NOT NULL COMMENT 'FK to users.id',
    `updated_by`    VARCHAR(36)     DEFAULT NULL COMMENT 'FK to users.id',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_taxonomy_company_period` (`company_id`, `reporting_period`),
    KEY `idx_taxonomy_company_id` (`company_id`),
    CONSTRAINT `fk_taxonomy_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_taxonomy_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_taxonomy_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='EU Taxonomy alignment reporting data';

-- ============================================================================
-- TABLE: assurance
-- Third-party assurance data
-- ============================================================================

CREATE TABLE IF NOT EXISTS `assurance` (
    `id`                VARCHAR(36)     NOT NULL COMMENT 'UUID primary key',
    `company_id`        VARCHAR(36)     NOT NULL COMMENT 'FK to companies.id',
    `reporting_period`  VARCHAR(7)      NOT NULL COMMENT 'Reporting period in YYYY-MM format',
    `provider`          VARCHAR(255)    DEFAULT NULL COMMENT 'Assurance provider name',
    `level`             ENUM('limited','reasonable') DEFAULT NULL COMMENT 'Assurance level',
    `standard`          VARCHAR(255)    DEFAULT NULL COMMENT 'Standard used (e.g. ISAE 3000)',
    `scope_description` TEXT            DEFAULT NULL COMMENT 'Scope of the assurance engagement',
    `conclusion`        TEXT            DEFAULT NULL COMMENT 'Assurance conclusion',
    `report_date`       DATE            DEFAULT NULL COMMENT 'Date of assurance report',
    `created_by`        VARCHAR(36)     NOT NULL COMMENT 'FK to users.id',
    `updated_by`        VARCHAR(36)     DEFAULT NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_assurance_company_period` (`company_id`, `reporting_period`),
    KEY `idx_assurance_company_id` (`company_id`),
    CONSTRAINT `fk_assurance_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assurance_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Third-party assurance data';

-- ============================================================================
-- TABLE: esrs2_general_disclosures
-- ESRS 2 General Disclosures (governance, materiality, value chain)
-- NOTE: This table is not generated automatically — added here manually.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `esrs2_general_disclosures` (
    `id`                               VARCHAR(36)  NOT NULL,
    `company_id`                       VARCHAR(36)  NOT NULL,
    `reporting_period`                 VARCHAR(7)   NOT NULL COMMENT 'YYYY-MM',
    `consolidation_scope`              TEXT         DEFAULT NULL,
    `value_chain_boundaries`           TEXT         DEFAULT NULL,
    `board_role_in_sustainability`     TEXT         DEFAULT NULL,
    `esg_integration_in_remuneration`  INT          DEFAULT NULL,
    `assessment_process`               TEXT         DEFAULT NULL,
    `created_by`                       VARCHAR(36)  NOT NULL,
    `updated_by`                       VARCHAR(36)  DEFAULT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_esrs2_company_period` (`company_id`, `reporting_period`),
    CONSTRAINT `fk_esrs2_company`
        FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA: Default company and admin user
-- ============================================================================

-- Default company
INSERT IGNORE INTO `companies` (`id`, `name`, `country_of_registration`, `created_at`, `updated_at`)
VALUES (
    'a1b2c3d4-0000-0000-0000-000000000001',
    'My Company',
    'MY',
    NOW(),
    NOW()
);

-- Default admin user (password: admin123 — change immediately in production!)
-- bcrypt hash of 'admin123'
INSERT IGNORE INTO `users` (`id`, `company_id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`)
VALUES (
    'b2c3d4e5-0000-0000-0000-000000000001',
    'a1b2c3d4-0000-0000-0000-000000000001',
    'Administrator',
    'admin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    NOW(),
    NOW()
);

-- ============================================================================
-- SEED DATA: Common emission factors (DEFRA 2024 approximations)
-- ============================================================================

INSERT IGNORE INTO `emission_factors` (`id`, `activity_type`, `scope`, `region`, `factor`, `unit`, `source`, `version`, `is_active`, `valid_from`) VALUES

-- Scope 1: Fuels
('ef-0001', 'diesel',           'Scope 1', 'GLOBAL', 2.68720, 'litre',    'DEFRA', '2024', 1, '2024-01-01'),
('ef-0002', 'petrol',           'Scope 1', 'GLOBAL', 2.31380, 'litre',    'DEFRA', '2024', 1, '2024-01-01'),
('ef-0003', 'natural_gas',      'Scope 1', 'GLOBAL', 2.04220, 'kWh',      'DEFRA', '2024', 1, '2024-01-01'),
('ef-0004', 'lpg',              'Scope 1', 'GLOBAL', 1.55540, 'litre',    'DEFRA', '2024', 1, '2024-01-01'),

-- Scope 2: Electricity grids (location-based)
('ef-0010', 'electricity',      'Scope 2 Location-Based', 'UK',     0.20765, 'kWh', 'DEFRA', '2024', 1, '2024-01-01'),
('ef-0011', 'electricity',      'Scope 2 Location-Based', 'MY',     0.58500, 'kWh', 'EIM',   '2023', 1, '2023-01-01'),
('ef-0012', 'electricity',      'Scope 2 Location-Based', 'US',     0.38600, 'kWh', 'EPA',   '2024', 1, '2024-01-01'),
('ef-0013', 'electricity',      'Scope 2 Location-Based', 'EU',     0.27600, 'kWh', 'EEA',   '2024', 1, '2024-01-01'),
('ef-0014', 'electricity',      'Scope 2 Location-Based', 'GLOBAL', 0.49000, 'kWh', 'IEA',   '2024', 1, '2024-01-01');

-- ============================================================================
COMMIT;
-- ============================================================================
