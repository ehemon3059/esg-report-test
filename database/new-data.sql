/*
SQLyog Community v13.1.6 (64 bit)
MySQL - 10.4.32-MariaDB : Database - esg_reporting_db
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`esg_reporting_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `esg_reporting_db`;

/*Table structure for table `assurance` */

DROP TABLE IF EXISTS `assurance`;

CREATE TABLE `assurance` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `company_id` varchar(36) NOT NULL COMMENT 'FK to companies.id',
  `reporting_period` varchar(7) NOT NULL COMMENT 'Reporting period in YYYY-MM format',
  `provider` varchar(255) DEFAULT NULL COMMENT 'Assurance provider name',
  `level` enum('limited','reasonable') DEFAULT NULL COMMENT 'Assurance level',
  `standard` varchar(255) DEFAULT NULL COMMENT 'Standard used (e.g. ISAE 3000)',
  `scope_description` text DEFAULT NULL COMMENT 'Scope of the assurance engagement',
  `conclusion` text DEFAULT NULL COMMENT 'Assurance conclusion',
  `report_date` date DEFAULT NULL COMMENT 'Date of assurance report',
  `created_by` varchar(36) NOT NULL COMMENT 'FK to users.id',
  `updated_by` varchar(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `checklist_data_collection_documented` tinyint(1) DEFAULT 0,
  `checklist_internal_controls_tested` tinyint(1) DEFAULT 0,
  `checklist_source_documentation_trail` tinyint(1) DEFAULT 0,
  `checklist_calculation_method_validated` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assurance_company_period` (`company_id`,`reporting_period`),
  KEY `idx_assurance_company_id` (`company_id`),
  KEY `fk_assurance_created_by` (`created_by`),
  CONSTRAINT `fk_assurance_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assurance_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Third-party assurance data';

/*Data for the table `assurance` */

insert  into `assurance`(`id`,`company_id`,`reporting_period`,`provider`,`level`,`standard`,`scope_description`,`conclusion`,`report_date`,`created_by`,`updated_by`,`created_at`,`updated_at`,`checklist_data_collection_documented`,`checklist_internal_controls_tested`,`checklist_source_documentation_trail`,`checklist_calculation_method_validated`) values 
('ed0199da-6fee-4708-a2b6-a6fe5a70aa9b','4d89a704-6ead-467c-87b3-4711a8028045','2026-03','BDO GmbH','limited','ISAE 3000','Limited assurance engagement covers GreenMake Industries\' Scope 1 and Scope 2 greenhouse gas emissions data, energy consumption records, and ESRS 2 general disclosures for the reporting period January 2025 to December 2025. All data sourced from Main Factory Berlin and Distribution Warehouse Hamburg sites. EU Taxonomy KPIs (eligible and aligned revenue, CapEx, OpEx) are included within scope. Social metrics (S1 workforce data, S4 product safety incidents) are excluded from this engagement.','Based on our limited assurance procedures, nothing has come to our attention that causes us to believe that the selected sustainability information for the period ending 31 December 2025 has not been prepared, in all material respects, in accordance with the applicable criteria. GHG emission calculations are consistent with DEFRA 2024 emission factors. No material misstatements were identified.','2026-03-15','2e6e9126-5b9a-4f10-88fa-66ae0338e4e5',NULL,'2026-03-31 17:53:50','2026-03-31 17:53:50',1,1,1,1);

/*Table structure for table `companies` */

DROP TABLE IF EXISTS `companies`;

CREATE TABLE `companies` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `name` varchar(255) NOT NULL COMMENT 'Company name',
  `country_of_registration` varchar(100) DEFAULT NULL COMMENT 'Country where company is registered',
  `industry` varchar(100) DEFAULT NULL COMMENT 'Industry sector',
  `registration_number` varchar(100) DEFAULT NULL COMMENT 'Legal registration number',
  `website` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Organization/company information';

/*Data for the table `companies` */

insert  into `companies`(`id`,`name`,`country_of_registration`,`industry`,`registration_number`,`website`,`description`,`created_at`,`updated_at`,`deleted_at`) values 
('4d89a704-6ead-467c-87b3-4711a8028045','GreenMake Industries','DE (Germany)','Manufacturing','HRB-2024-88512','https://www.greenmake-industries.com','Mid-size manufacturing company producing industrial components. One main factory and one distribution warehouse in central Germany.','2026-03-28 11:51:44','2026-03-31 17:31:17',NULL),
('79e517d3-9b1e-4126-a380-660e8b02cf13','PharmaGreen Sdn Bhd','MY (Malaysia)','Healthcare & Pharmaceuticals','PG-2019-00481','','','2026-03-28 15:15:53','2026-03-28 15:18:45',NULL),
('a1b2c3d4-0000-0000-0000-000000000001','My Company','MY',NULL,NULL,NULL,NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07',NULL);

/*Table structure for table `emission_factors` */

DROP TABLE IF EXISTS `emission_factors`;

CREATE TABLE `emission_factors` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `activity_type` varchar(100) NOT NULL COMMENT 'Type of activity (e.g. diesel, electricity_grid_uk)',
  `scope` enum('Scope 1','Scope 2 Location-Based','Scope 2 Market-Based','Scope 3') NOT NULL COMMENT 'GHG Protocol scope',
  `region` varchar(100) DEFAULT NULL COMMENT 'Geographic region for the factor',
  `factor` decimal(18,8) NOT NULL COMMENT 'kg CO2e per unit of activity',
  `unit` varchar(50) NOT NULL COMMENT 'Unit of the activity (e.g. litre, kWh)',
  `source` varchar(255) DEFAULT NULL COMMENT 'Data source (e.g. DEFRA 2024)',
  `version` varchar(50) DEFAULT NULL COMMENT 'Version of the emission factor set',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = inactive',
  `valid_from` timestamp NULL DEFAULT NULL COMMENT 'Start of validity period',
  `valid_until` timestamp NULL DEFAULT NULL COMMENT 'End of validity period (NULL = still valid)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ef_activity_scope` (`activity_type`,`scope`),
  KEY `idx_ef_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CO2e emission conversion factors reference library';

/*Data for the table `emission_factors` */

insert  into `emission_factors`(`id`,`activity_type`,`scope`,`region`,`factor`,`unit`,`source`,`version`,`is_active`,`valid_from`,`valid_until`,`created_at`,`updated_at`) values 
('3080b3da-d9d2-428b-b1ac-2585e2a2aa3c','electricity','Scope 2 Location-Based','MY',0.58500000,'kWh','Custom','2024',1,'2024-01-01 00:00:00',NULL,'2026-03-28 15:32:01','2026-03-28 15:32:01'),
('ef-0001','diesel','Scope 1','GLOBAL',2.68720000,'litre','DEFRA','2024',1,'2024-01-01 06:00:00',NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07'),
('ef-0002','petrol','Scope 1','GLOBAL',2.31380000,'litre','DEFRA','2024',1,'2024-01-01 06:00:00',NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07'),
('ef-0003','natural_gas','Scope 1','GLOBAL',2.04220000,'kWh','DEFRA','2024',1,'2024-01-01 06:00:00',NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07'),
('ef-0004','lpg','Scope 1','GLOBAL',1.55540000,'litre','DEFRA','2024',1,'2024-01-01 06:00:00',NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07'),
('ef-0010','electricity','Scope 2 Location-Based','UK',0.20765000,'kWh','DEFRA','2024',1,'2024-01-01 06:00:00',NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07'),
('ef-0011','electricity','Scope 2 Location-Based','MY',0.58500000,'kWh','EIM','2023',1,'2023-01-01 06:00:00',NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07'),
('ef-0012','electricity','Scope 2 Location-Based','US',0.38600000,'kWh','EPA','2024',1,'2024-01-01 06:00:00',NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07'),
('ef-0013','electricity','Scope 2 Location-Based','EU',0.27600000,'kWh','EEA','2024',1,'2024-01-01 06:00:00',NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07'),
('ef-0014','electricity','Scope 2 Location-Based','GLOBAL',0.49000000,'kWh','IEA','2024',1,'2024-01-01 06:00:00',NULL,'2026-03-28 11:01:07','2026-03-28 11:01:07');

/*Table structure for table `emission_records` */

DROP TABLE IF EXISTS `emission_records`;

CREATE TABLE `emission_records` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `company_id` varchar(36) NOT NULL COMMENT 'FK to companies.id',
  `scope` enum('Scope 1','Scope 2 Location-Based','Scope 2 Market-Based','Scope 3') NOT NULL COMMENT 'GHG Protocol scope',
  `tco2e_calculated` decimal(18,6) NOT NULL COMMENT 'Calculated emissions in tonnes CO2 equivalent',
  `energy_activity_id` varchar(36) DEFAULT NULL COMMENT 'FK to energy_activities.id (if from energy)',
  `fuel_activity_id` varchar(36) DEFAULT NULL COMMENT 'FK to fuel_activities.id (if from fuel)',
  `emission_factor_id` varchar(36) NOT NULL COMMENT 'FK to emission_factors.id used for calculation',
  `date_calculated` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When this record was calculated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_er_company_id` (`company_id`),
  KEY `idx_er_scope` (`scope`),
  KEY `idx_er_date_calculated` (`date_calculated`),
  KEY `idx_er_energy_activity_id` (`energy_activity_id`),
  KEY `idx_er_fuel_activity_id` (`fuel_activity_id`),
  KEY `fk_er_emission_factor` (`emission_factor_id`),
  CONSTRAINT `fk_er_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_er_emission_factor` FOREIGN KEY (`emission_factor_id`) REFERENCES `emission_factors` (`id`),
  CONSTRAINT `fk_er_energy_activity` FOREIGN KEY (`energy_activity_id`) REFERENCES `energy_activities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_er_fuel_activity` FOREIGN KEY (`fuel_activity_id`) REFERENCES `fuel_activities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Calculated GHG emission records';

/*Data for the table `emission_records` */

insert  into `emission_records`(`id`,`company_id`,`scope`,`tco2e_calculated`,`energy_activity_id`,`fuel_activity_id`,`emission_factor_id`,`date_calculated`,`created_at`) values 
('4ca4f0d2-ae7a-4fee-b580-e34783080e28','4d89a704-6ead-467c-87b3-4711a8028045','Scope 2 Location-Based',4.165000,'fce0fb67-a0b6-4506-a65e-3cd24013a921',NULL,'ef-0014','2026-03-31 17:36:05','2026-03-31 17:36:05'),
('903b93e8-a6ab-4d5a-9ad3-768832a99a14','4d89a704-6ead-467c-87b3-4711a8028045','Scope 1',2.149760,NULL,'dd7449fc-dfb8-4df3-bb2e-cf3947d2503c','ef-0001','2026-03-31 17:34:47','2026-03-31 17:34:47'),
('a6a81685-3113-452d-b988-04ceb41ac1cd','4d89a704-6ead-467c-87b3-4711a8028045','Scope 2 Location-Based',22.050000,'30795e9a-c4cf-4cf8-a7db-76128cd63bc3',NULL,'ef-0014','2026-03-31 17:35:43','2026-03-31 17:35:43'),
('b7081a05-6bdd-4368-84e5-75a000bd5aab','4d89a704-6ead-467c-87b3-4711a8028045','Scope 1',6.718000,NULL,'512a457e-155d-49f3-b578-708b4ae2db06','ef-0001','2026-03-31 17:33:16','2026-03-31 17:33:16'),
('faac5019-9e51-4768-ab55-8e847366bb5c','4d89a704-6ead-467c-87b3-4711a8028045','Scope 1',16.337600,NULL,'1803cac0-7e9f-4a72-a68c-27c2222e53bb','ef-0003','2026-03-31 17:34:04','2026-03-31 17:34:04');

/*Table structure for table `energy_activities` */

DROP TABLE IF EXISTS `energy_activities`;

CREATE TABLE `energy_activities` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `site_id` varchar(36) NOT NULL COMMENT 'FK to sites.id',
  `date` date NOT NULL COMMENT 'Date of energy consumption',
  `energy_type` varchar(100) NOT NULL COMMENT 'Type of energy (e.g. electricity, heat)',
  `consumption` decimal(15,4) NOT NULL COMMENT 'Amount consumed',
  `unit` varchar(50) NOT NULL COMMENT 'Unit of consumption (e.g. kWh)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ea_site_id` (`site_id`),
  KEY `idx_ea_date` (`date`),
  CONSTRAINT `fk_ea_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Energy consumption activities (Scope 2)';

/*Data for the table `energy_activities` */

insert  into `energy_activities`(`id`,`site_id`,`date`,`energy_type`,`consumption`,`unit`,`created_at`,`updated_at`) values 
('30795e9a-c4cf-4cf8-a7db-76128cd63bc3','3639da77-cb15-4d2f-9dad-c8ac92a9a629','2026-01-01','electricity',45000.0000,'kWh','2026-03-31 17:35:43','2026-03-31 17:35:43'),
('fce0fb67-a0b6-4506-a65e-3cd24013a921','bec1dc31-b1a7-4cfd-ae1d-3f2932e7dd8d','2026-01-01','electricity',8500.0000,'kWh','2026-03-31 17:36:05','2026-03-31 17:36:05');

/*Table structure for table `environmental_topics` */

DROP TABLE IF EXISTS `environmental_topics`;

CREATE TABLE `environmental_topics` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `company_id` varchar(36) NOT NULL COMMENT 'FK to companies.id',
  `reporting_period` varchar(7) NOT NULL COMMENT 'Reporting period in YYYY-MM format',
  `status` enum('DRAFT','UNDER_REVIEW','APPROVED','PUBLISHED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `e1_material` tinyint(1) DEFAULT 0 COMMENT '1 = material topic, 0 = not material',
  `e1_climate_policy` text DEFAULT NULL COMMENT 'Climate policy description',
  `e1_reduction_target` text DEFAULT NULL COMMENT 'Emission reduction targets',
  `e2_material` tinyint(1) DEFAULT 0,
  `e2_nox_t_per_year` decimal(15,4) DEFAULT NULL COMMENT 'NOx emissions in tonnes per year',
  `e2_sox_t_per_year` decimal(15,4) DEFAULT NULL COMMENT 'SOx emissions in tonnes per year',
  `e3_material` tinyint(1) DEFAULT 0,
  `e3_water_withdrawal_m3` decimal(15,4) DEFAULT NULL COMMENT 'Total water withdrawal in m3',
  `e3_water_recycling_rate_pct` int(11) DEFAULT NULL COMMENT 'Water recycling rate 0-100%',
  `e4_material` tinyint(1) DEFAULT 0,
  `e4_protected_areas_impact` text DEFAULT NULL COMMENT 'Impact on protected areas description',
  `e5_material` tinyint(1) DEFAULT 0,
  `e5_recycling_rate_pct` int(11) DEFAULT NULL COMMENT 'Overall recycling rate 0-100%',
  `e5_recycled_input_materials_pct` int(11) DEFAULT NULL COMMENT 'Recycled input materials rate 0-100%',
  `created_by` varchar(36) NOT NULL COMMENT 'FK to users.id',
  `updated_by` varchar(36) DEFAULT NULL COMMENT 'FK to users.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_env_company_period` (`company_id`,`reporting_period`),
  KEY `idx_env_company_id` (`company_id`),
  KEY `fk_env_created_by` (`created_by`),
  KEY `fk_env_updated_by` (`updated_by`),
  CONSTRAINT `fk_env_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_env_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_env_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ESRS E1-E5 environmental reporting data';

/*Data for the table `environmental_topics` */

insert  into `environmental_topics`(`id`,`company_id`,`reporting_period`,`status`,`e1_material`,`e1_climate_policy`,`e1_reduction_target`,`e2_material`,`e2_nox_t_per_year`,`e2_sox_t_per_year`,`e3_material`,`e3_water_withdrawal_m3`,`e3_water_recycling_rate_pct`,`e4_material`,`e4_protected_areas_impact`,`e5_material`,`e5_recycling_rate_pct`,`e5_recycled_input_materials_pct`,`created_by`,`updated_by`,`created_at`,`updated_at`) values 
('c9d18417-d14f-470d-9746-ddd458b3160b','4d89a704-6ead-467c-87b3-4711a8028045','2026-03','DRAFT',1,'GreenMake is committed to net-zero Scope 1 and 2 emissions by 2040. Annual carbon budgets are set per site. Renewable energy procurement is prioritised for all new contracts.','30% reduction in Scope 1 and 2 emissions by 2030 vs 2024 baseline',1,1.2000,0.4500,1,12500.0000,35,0,'No operational sites are located adjacent to Natura 2000 or other protected areas. No significant biodiversity impact identified.',0,62,28,'2e6e9126-5b9a-4f10-88fa-66ae0338e4e5',NULL,'2026-03-31 17:44:36','2026-03-31 17:44:36');

/*Table structure for table `esrs2_general_disclosures` */

DROP TABLE IF EXISTS `esrs2_general_disclosures`;

CREATE TABLE `esrs2_general_disclosures` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `reporting_period` varchar(7) NOT NULL COMMENT 'YYYY-MM',
  `consolidation_scope` text DEFAULT NULL,
  `value_chain_boundaries` text DEFAULT NULL,
  `board_role_in_sustainability` text DEFAULT NULL,
  `esg_integration_in_remuneration` int(11) DEFAULT NULL,
  `assessment_process` text DEFAULT NULL,
  `created_by` varchar(36) NOT NULL,
  `updated_by` varchar(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_esrs2_company_period` (`company_id`,`reporting_period`),
  CONSTRAINT `fk_esrs2_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `esrs2_general_disclosures` */

insert  into `esrs2_general_disclosures`(`id`,`company_id`,`reporting_period`,`consolidation_scope`,`value_chain_boundaries`,`board_role_in_sustainability`,`esg_integration_in_remuneration`,`assessment_process`,`created_by`,`updated_by`,`created_at`,`updated_at`) values 
('66868bf0-85bf-4c68-a5db-4acc4d41a1ff','4d89a704-6ead-467c-87b3-4711a8028045','2026-03','This report covers GreenMake Industries GmbH and its two wholly-owned operational sites: Main Factory Berlin and Distribution Warehouse Hamburg. Financial control method applied.','Upstream assessment includes Tier 1 direct material suppliers. Downstream includes distribution partners and end-customer product delivery. Contract manufacturers excluded from Scope 3.','The Sustainability Committee of the Board meets quarterly to review ESG KPIs against annual targets. The CEO holds ultimate accountability for climate strategy.',20,'Double materiality assessment conducted via stakeholder surveys (employees, suppliers, customers), benchmarking against ESRS sector standards, and two expert workshop sessions in Q4 2025.','2e6e9126-5b9a-4f10-88fa-66ae0338e4e5','2e6e9126-5b9a-4f10-88fa-66ae0338e4e5','2026-03-31 17:38:18','2026-03-31 17:38:26');

/*Table structure for table `eu_taxonomy` */

DROP TABLE IF EXISTS `eu_taxonomy`;

CREATE TABLE `eu_taxonomy` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `company_id` varchar(36) NOT NULL COMMENT 'FK to companies.id',
  `reporting_period` varchar(7) NOT NULL COMMENT 'Reporting period in YYYY-MM format',
  `status` enum('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `economic_activities` text DEFAULT NULL COMMENT 'Description of economic activities assessed',
  `technical_screening_criteria` text DEFAULT NULL COMMENT 'Technical screening criteria applied',
  `taxonomy_eligible_revenue_pct` int(11) DEFAULT NULL COMMENT 'Taxonomy-eligible revenue %',
  `taxonomy_aligned_revenue_pct` int(11) DEFAULT NULL COMMENT 'Taxonomy-aligned revenue %',
  `taxonomy_eligible_capex_pct` int(11) DEFAULT NULL COMMENT 'Taxonomy-eligible CapEx %',
  `taxonomy_aligned_capex_pct` int(11) DEFAULT NULL COMMENT 'Taxonomy-aligned CapEx %',
  `taxonomy_aligned_opex_pct` int(11) DEFAULT NULL COMMENT 'Taxonomy-aligned OpEx %',
  `dnsh_status` enum('ALL_OBJECTIVES_PASSED','SOME_OBJECTIVES_NOT_MET','ASSESSMENT_IN_PROGRESS') DEFAULT NULL COMMENT 'Do No Significant Harm assessment status',
  `social_safeguards_status` enum('FULL_COMPLIANCE','NON_COMPLIANCE','PARTIAL_REMEDIATION') DEFAULT NULL COMMENT 'Minimum social safeguards status',
  `created_by` varchar(36) NOT NULL COMMENT 'FK to users.id',
  `updated_by` varchar(36) DEFAULT NULL COMMENT 'FK to users.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_taxonomy_company_period` (`company_id`,`reporting_period`),
  KEY `idx_taxonomy_company_id` (`company_id`),
  KEY `fk_taxonomy_created_by` (`created_by`),
  KEY `fk_taxonomy_updated_by` (`updated_by`),
  CONSTRAINT `fk_taxonomy_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_taxonomy_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_taxonomy_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='EU Taxonomy alignment reporting data';

/*Data for the table `eu_taxonomy` */

insert  into `eu_taxonomy`(`id`,`company_id`,`reporting_period`,`status`,`economic_activities`,`technical_screening_criteria`,`taxonomy_eligible_revenue_pct`,`taxonomy_aligned_revenue_pct`,`taxonomy_eligible_capex_pct`,`taxonomy_aligned_capex_pct`,`taxonomy_aligned_opex_pct`,`dnsh_status`,`social_safeguards_status`,`created_by`,`updated_by`,`created_at`,`updated_at`) values 
('188762ad-6ff4-43f9-8e04-fd3a4dd95b4e','4d89a704-6ead-467c-87b3-4711a8028045','2026-03','DRAFT','Manufacturing of industrial metal components (NACE C25.6). Assembly and testing of mechanical systems (NACE C28.2).','Activities assessed against EU Taxonomy Climate Change Mitigation criteria. Substantial contribution threshold evaluated for energy efficiency in manufacturing processes.',45,22,38,18,15,'SOME_OBJECTIVES_NOT_MET','FULL_COMPLIANCE','2e6e9126-5b9a-4f10-88fa-66ae0338e4e5',NULL,'2026-03-31 17:51:01','2026-03-31 17:51:01');

/*Table structure for table `fuel_activities` */

DROP TABLE IF EXISTS `fuel_activities`;

CREATE TABLE `fuel_activities` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `site_id` varchar(36) NOT NULL COMMENT 'FK to sites.id',
  `date` date NOT NULL COMMENT 'Date of fuel consumption',
  `fuel_type` varchar(100) NOT NULL COMMENT 'Type of fuel (e.g. diesel, natural_gas)',
  `volume` decimal(15,4) NOT NULL COMMENT 'Volume consumed',
  `unit` varchar(50) NOT NULL COMMENT 'Unit of volume (e.g. litre, m3)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fa_site_id` (`site_id`),
  KEY `idx_fa_date` (`date`),
  CONSTRAINT `fk_fa_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fuel consumption activities (Scope 1)';

/*Data for the table `fuel_activities` */

insert  into `fuel_activities`(`id`,`site_id`,`date`,`fuel_type`,`volume`,`unit`,`created_at`,`updated_at`) values 
('1803cac0-7e9f-4a72-a68c-27c2222e53bb','3639da77-cb15-4d2f-9dad-c8ac92a9a629','2026-02-10','natural_gas',8000.0000,'m3','2026-03-31 17:34:04','2026-03-31 17:34:04'),
('512a457e-155d-49f3-b578-708b4ae2db06','3639da77-cb15-4d2f-9dad-c8ac92a9a629','2026-01-15','diesel',2500.0000,'litre','2026-03-31 17:33:16','2026-03-31 17:33:16'),
('dd7449fc-dfb8-4df3-bb2e-cf3947d2503c','bec1dc31-b1a7-4cfd-ae1d-3f2932e7dd8d','2026-01-20','diesel',800.0000,'litre','2026-03-31 17:34:47','2026-03-31 17:34:47');

/*Table structure for table `s_governance` */

DROP TABLE IF EXISTS `s_governance`;

CREATE TABLE `s_governance` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `company_id` varchar(36) NOT NULL COMMENT 'FK to companies.id',
  `reporting_period` varchar(7) NOT NULL COMMENT 'Reporting period in YYYY-MM format',
  `status` enum('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `g1_board_composition_independence` text DEFAULT NULL COMMENT 'Board independence and composition details',
  `g1_gender_diversity_pct` int(11) DEFAULT NULL COMMENT 'Board gender diversity percentage 0-100',
  `g1_esg_oversight` text DEFAULT NULL COMMENT 'ESG oversight mechanisms',
  `g1_whistleblower_cases` text DEFAULT NULL COMMENT 'Whistleblower cases description',
  `g1_anti_corruption_policies` text DEFAULT NULL COMMENT 'Anti-corruption and bribery policies',
  `g1_related_party_controls` text DEFAULT NULL COMMENT 'Related party transaction controls',
  `created_by` varchar(36) NOT NULL COMMENT 'FK to users.id',
  `updated_by` varchar(36) DEFAULT NULL COMMENT 'FK to users.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gov_company_period` (`company_id`,`reporting_period`),
  KEY `idx_gov_company_id` (`company_id`),
  KEY `fk_gov_created_by` (`created_by`),
  KEY `fk_gov_updated_by` (`updated_by`),
  CONSTRAINT `fk_gov_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gov_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_gov_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ESRS G1 governance reporting data';

/*Data for the table `s_governance` */

insert  into `s_governance`(`id`,`company_id`,`reporting_period`,`status`,`g1_board_composition_independence`,`g1_gender_diversity_pct`,`g1_esg_oversight`,`g1_whistleblower_cases`,`g1_anti_corruption_policies`,`g1_related_party_controls`,`created_by`,`updated_by`,`created_at`,`updated_at`) values 
('f098a98b-a653-45cc-b3f6-34e498ad6cbf','4d89a704-6ead-467c-87b3-4711a8028045','2026-03','DRAFT','Board of 7 directors: 4 independent non-executives, 2 executive directors (CEO, CFO), 1 employee representative. Audit and Sustainability sub-committees in place.',43,'ESG risks integrated into the Board\'s enterprise risk register. Sustainability Committee reviews ESG KPIs quarterly. External ESG audit conducted annually.','2 reports received via anonymous hotline in 2025. Both investigated — 1 resolved (process improvement implemented), 1 ongoing.','ISO 37001 Anti-Bribery Management System certified. Zero-tolerance Code of Conduct applies to all staff and suppliers. Mandatory annual training completed by 100% of employees.','All related-party transactions require Audit Committee pre-approval. No material related-party transactions occurred in 2025.','2e6e9126-5b9a-4f10-88fa-66ae0338e4e5',NULL,'2026-03-31 17:48:15','2026-03-31 17:48:15');

/*Table structure for table `scope3_activities` */

DROP TABLE IF EXISTS `scope3_activities`;

CREATE TABLE `scope3_activities` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `reporting_period` varchar(7) NOT NULL COMMENT 'YYYY-MM',
  `category` varchar(100) NOT NULL COMMENT 'GHG Protocol Category 1-15',
  `description` text DEFAULT NULL,
  `tco2e_estimated` decimal(18,4) DEFAULT NULL,
  `estimation_method` varchar(255) DEFAULT NULL,
  `data_quality` enum('measured','calculated','estimated') DEFAULT 'estimated',
  `created_by` varchar(36) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scope3_company_period` (`company_id`,`reporting_period`),
  KEY `fk_scope3_created_by` (`created_by`),
  CONSTRAINT `fk_scope3_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scope3_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `scope3_activities` */

/*Table structure for table `sites` */

DROP TABLE IF EXISTS `sites`;

CREATE TABLE `sites` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `company_id` varchar(36) NOT NULL COMMENT 'FK to companies.id',
  `name` varchar(255) NOT NULL COMMENT 'Site/facility name',
  `address` text DEFAULT NULL COMMENT 'Full address',
  `country` varchar(100) DEFAULT NULL COMMENT 'Country code or name',
  `created_by` varchar(36) NOT NULL COMMENT 'FK to users.id',
  `updated_by` varchar(36) DEFAULT NULL COMMENT 'FK to users.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  `deleted_by` varchar(36) DEFAULT NULL COMMENT 'FK to users.id',
  PRIMARY KEY (`id`),
  KEY `idx_sites_company_id` (`company_id`),
  KEY `idx_sites_created_by` (`created_by`),
  KEY `fk_sites_updated_by` (`updated_by`),
  CONSTRAINT `fk_sites_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sites_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_sites_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Facility and location data';

/*Data for the table `sites` */

insert  into `sites`(`id`,`company_id`,`name`,`address`,`country`,`created_by`,`updated_by`,`created_at`,`updated_at`,`deleted_at`,`deleted_by`) values 
('3639da77-cb15-4d2f-9dad-c8ac92a9a629','4d89a704-6ead-467c-87b3-4711a8028045','Main Factory','Industriestraße 45, 10115 Berlin','DE','2e6e9126-5b9a-4f10-88fa-66ae0338e4e5',NULL,'2026-03-31 17:31:51','2026-03-31 17:31:51',NULL,NULL),
('44991cf5-4059-44de-a99e-2e4207124ccc','79e517d3-9b1e-4126-a380-660e8b02cf13','Cold Storage Facility','Jalan Gudang 12, Port Klang, Selangor','MY (Malaysia)','0515d3d8-a326-4c8b-9ba4-873c19b36b9b',NULL,'2026-03-28 15:20:07','2026-03-28 15:20:07',NULL,NULL),
('4c116e4d-4fd3-4f57-95d8-db102132f88a','79e517d3-9b1e-4126-a380-660e8b02cf13','Manufacturing Plant A','Lot 7, Industrial Park, Shah Alam, Selangor','MY (Malaysia)','0515d3d8-a326-4c8b-9ba4-873c19b36b9b',NULL,'2026-03-28 15:19:43','2026-03-28 15:19:43',NULL,NULL),
('4e203798-c9ce-450e-bd52-f3797741e5fa','79e517d3-9b1e-4126-a380-660e8b02cf13','PharmaGreen Sdn Bhd','','MY (Malaysia)','0515d3d8-a326-4c8b-9ba4-873c19b36b9b',NULL,'2026-03-28 15:17:07','2026-03-28 15:19:05','2026-03-28 15:19:05','0515d3d8-a326-4c8b-9ba4-873c19b36b9b'),
('6b7a4a8f-2038-4f55-9b26-04526545419e','79e517d3-9b1e-4126-a380-660e8b02cf13','HQ & R&D Centre','Jalan Bio 3, Bukit Jalil, Kuala Lumpur','MY (Malaysia)','0515d3d8-a326-4c8b-9ba4-873c19b36b9b','0515d3d8-a326-4c8b-9ba4-873c19b36b9b','2026-03-28 15:19:21','2026-03-28 15:20:13',NULL,NULL),
('bec1dc31-b1a7-4cfd-ae1d-3f2932e7dd8d','4d89a704-6ead-467c-87b3-4711a8028045','Distribution Warehouse','Lagerweg 12, 20095 Hamburg','DE','2e6e9126-5b9a-4f10-88fa-66ae0338e4e5',NULL,'2026-03-31 17:32:10','2026-03-31 17:32:10',NULL,NULL);

/*Table structure for table `social_topics` */

DROP TABLE IF EXISTS `social_topics`;

CREATE TABLE `social_topics` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `company_id` varchar(36) NOT NULL COMMENT 'FK to companies.id',
  `reporting_period` varchar(7) NOT NULL COMMENT 'Reporting period in YYYY-MM format',
  `status` enum('DRAFT','UNDER_REVIEW','APPROVED','PUBLISHED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `s1_material` tinyint(1) DEFAULT 0 COMMENT '1 = material topic',
  `s1_employee_count_by_contract` text DEFAULT NULL COMMENT 'Employee breakdown by contract type',
  `s1_health_and_safety` text DEFAULT NULL COMMENT 'Health and safety disclosures',
  `s1_training_hours_per_employee` int(11) DEFAULT NULL COMMENT 'Average training hours per employee',
  `s2_material` tinyint(1) DEFAULT 0,
  `s2_pct_suppliers_audited` int(11) DEFAULT NULL COMMENT 'Percentage of suppliers audited 0-100',
  `s2_remediation_actions` text DEFAULT NULL COMMENT 'Remediation actions taken',
  `s3_material` tinyint(1) DEFAULT 0,
  `s3_community_engagement` text DEFAULT NULL COMMENT 'Community engagement activities',
  `s3_complaints_and_outcomes` text DEFAULT NULL COMMENT 'Community complaints and outcomes',
  `s4_material` tinyint(1) DEFAULT 0,
  `s4_product_safety_incidents` int(11) DEFAULT NULL COMMENT 'Number of product safety incidents',
  `s4_consumer_remediation` text DEFAULT NULL COMMENT 'Consumer remediation actions',
  `created_by` varchar(36) NOT NULL COMMENT 'FK to users.id',
  `updated_by` varchar(36) DEFAULT NULL COMMENT 'FK to users.id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_social_company_period` (`company_id`,`reporting_period`),
  KEY `idx_social_company_id` (`company_id`),
  KEY `fk_social_created_by` (`created_by`),
  KEY `fk_social_updated_by` (`updated_by`),
  CONSTRAINT `fk_social_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_social_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_social_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ESRS S1-S4 social reporting data';

/*Data for the table `social_topics` */

insert  into `social_topics`(`id`,`company_id`,`reporting_period`,`status`,`s1_material`,`s1_employee_count_by_contract`,`s1_health_and_safety`,`s1_training_hours_per_employee`,`s2_material`,`s2_pct_suppliers_audited`,`s2_remediation_actions`,`s3_material`,`s3_community_engagement`,`s3_complaints_and_outcomes`,`s4_material`,`s4_product_safety_incidents`,`s4_consumer_remediation`,`created_by`,`updated_by`,`created_at`,`updated_at`) values 
('2227b864-19ac-467f-a713-2b90358f0fb8','4d89a704-6ead-467c-87b3-4711a8028045','2026-03','DRAFT',1,'Full-time: 210, Part-time: 45, Fixed-term: 30, Contractors: 15','ISO 45001 certified. Lost Time Injury Rate: 1.8 per 100 employees. Monthly safety audits conducted at all sites. Zero fatalities in 2025.',32,1,40,'Three suppliers flagged for excessive overtime in Q2 2025. Corrective action plans issued. Two suppliers completed remediation by Q4 2025. One is under monitoring.',0,'Annual community open day held at the Berlin factory. Partnerships with two local vocational training schools. EUR 25,000 donated to local social initiatives in 2025.','Two noise complaints received from neighbouring residents. Both resolved via scheduling changes to restrict night-time heavy machinery use.',1,3,'Three minor product defect reports received. Full root cause analysis completed. Affected batch recalled and replaced. Quality control checklist updated and retrained across production line.','2e6e9126-5b9a-4f10-88fa-66ae0338e4e5','2e6e9126-5b9a-4f10-88fa-66ae0338e4e5','2026-03-31 17:46:32','2026-03-31 17:47:00');

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` varchar(36) NOT NULL COMMENT 'UUID primary key',
  `company_id` varchar(36) NOT NULL COMMENT 'FK to companies.id',
  `name` varchar(255) NOT NULL COMMENT 'Full name',
  `email` varchar(255) NOT NULL COMMENT 'Login email (unique)',
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hashed password',
  `role` enum('admin','user','viewer') NOT NULL DEFAULT 'user' COMMENT 'User role',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_company_id` (`company_id`),
  CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User accounts and authentication';

/*Data for the table `users` */

insert  into `users`(`id`,`company_id`,`name`,`email`,`password`,`role`,`created_at`,`updated_at`,`deleted_at`) values 
('0515d3d8-a326-4c8b-9ba4-873c19b36b9b','79e517d3-9b1e-4126-a380-660e8b02cf13','Dr. Sarah Chen','admin@pharmagreen.com','$2y$10$FcoiM2Fzhra6gbOMBl3EXu/PlViP5CmrHEvpIu9fCp2eXqMjFz52O','admin','2026-03-28 15:15:53','2026-04-07 14:26:16',NULL),
('2e6e9126-5b9a-4f10-88fa-66ae0338e4e5','4d89a704-6ead-467c-87b3-4711a8028045','Md Emran Hossain Emon','eh.emon3059@gmail.com','$2y$10$FcoiM2Fzhra6gbOMBl3EXu/PlViP5CmrHEvpIu9fCp2eXqMjFz52O','admin','2026-03-28 11:51:45','2026-03-28 11:51:45',NULL),
('b2c3d4e5-0000-0000-0000-000000000001','a1b2c3d4-0000-0000-0000-000000000001','Administrator','admin@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin','2026-03-28 11:01:07','2026-03-28 11:01:07',NULL);

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
