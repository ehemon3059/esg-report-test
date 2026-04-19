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
  `id` char(36) NOT NULL,
  `company_id` char(36) NOT NULL,
  `reporting_period` datetime NOT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `status` enum('DRAFT','UNDER_REVIEW','APPROVED','PUBLISHED','REJECTED') DEFAULT 'DRAFT',
  `assurance_provider` varchar(255) DEFAULT NULL,
  `scope_of_assurance` enum('LIMITED','REASONABLE','MIXED') DEFAULT NULL,
  `reporting_standards` varchar(255) DEFAULT NULL,
  `assurance_conclusion_summary` text DEFAULT NULL,
  `assurance_report_url` varchar(500) DEFAULT NULL,
  `assurance_report_filename` varchar(255) DEFAULT NULL,
  `assurance_report_mime` varchar(100) DEFAULT NULL,
  `assurance_report_size_bytes` int(11) DEFAULT NULL,
  `material_misstatements_identified` text DEFAULT NULL,
  `management_response` text DEFAULT NULL,
  `checklist_data_collection_documented` tinyint(1) DEFAULT 0,
  `checklist_internal_controls_tested` tinyint(1) DEFAULT 0,
  `checklist_source_documentation_trail` tinyint(1) DEFAULT 0,
  `checklist_calculation_method_validated` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_assurance_company_period` (`company_id`,`reporting_period`),
  KEY `idx_assurance_company_id` (`company_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `assurance_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assurance_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `assurance_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `assurance` */

/*Table structure for table `companies` */

DROP TABLE IF EXISTS `companies`;

CREATE TABLE `companies` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `legal_entity` varchar(255) NOT NULL,
  `industry` varchar(255) NOT NULL,
  `country_of_registration` varchar(100) DEFAULT 'Germany',
  `created_by` char(36) DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_companies_name` (`name`),
  KEY `fk_companies_created_by` (`created_by`),
  KEY `fk_companies_updated_by` (`updated_by`),
  KEY `fk_companies_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_companies_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_companies_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_companies_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `companies` */

insert  into `companies`(`id`,`name`,`legal_entity`,`industry`,`country_of_registration`,`created_by`,`updated_by`,`deleted_at`,`deleted_by`,`created_at`,`updated_at`) values 
('cb319234-ddea-4f85-ba8f-ce7cb81f89e0','EH Web Dev','','Manufacturing','Bangladesh',NULL,NULL,NULL,NULL,'2026-04-17 11:40:03','2026-04-17 11:40:03');

/*Table structure for table `emission_factors` */

DROP TABLE IF EXISTS `emission_factors`;

CREATE TABLE `emission_factors` (
  `id` char(36) NOT NULL,
  `scope` varchar(100) NOT NULL,
  `activity_type` varchar(255) NOT NULL,
  `region` varchar(255) NOT NULL,
  `factor` decimal(15,6) NOT NULL,
  `unit` varchar(100) NOT NULL,
  `version` varchar(50) NOT NULL,
  `source` varchar(100) DEFAULT NULL,
  `valid_from` datetime DEFAULT current_timestamp(),
  `valid_until` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_emission_factors` (`activity_type`,`region`,`version`),
  KEY `idx_emission_factors_active` (`is_active`,`valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `emission_factors` */

insert  into `emission_factors`(`id`,`scope`,`activity_type`,`region`,`factor`,`unit`,`version`,`source`,`valid_from`,`valid_until`,`is_active`,`created_at`,`updated_at`) values 
('31291c8f-8cd5-4eb6-90d9-0507d5898b1c','Scope 1','diesel','GLOBAL',2.680000,'litre','2024','DEFRA','2024-01-01 00:00:00',NULL,1,'2026-04-17 11:53:13','2026-04-17 11:53:13'),
('c7d671ce-2bc8-4b03-921b-92034d20b09f','Scope 2 Location-Based','electricity','GLOBAL',0.420000,'kWh','2024','IEA','2024-01-01 00:00:00',NULL,1,'2026-04-17 11:53:27','2026-04-17 11:53:27');

/*Table structure for table `emission_records` */

DROP TABLE IF EXISTS `emission_records`;

CREATE TABLE `emission_records` (
  `id` char(36) NOT NULL,
  `company_id` char(36) NOT NULL,
  `report_id` char(36) DEFAULT NULL,
  `scope` varchar(100) NOT NULL,
  `tco2e_calculated` decimal(15,4) NOT NULL,
  `date_calculated` datetime DEFAULT current_timestamp(),
  `energy_activity_id` char(36) DEFAULT NULL,
  `fuel_activity_id` char(36) DEFAULT NULL,
  `emission_factor_id` char(36) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `energy_activity_id` (`energy_activity_id`),
  UNIQUE KEY `fuel_activity_id` (`fuel_activity_id`),
  KEY `idx_emission_records_company_scope` (`company_id`,`scope`),
  KEY `emission_factor_id` (`emission_factor_id`),
  KEY `fk_emission_records_report_id` (`report_id`),
  CONSTRAINT `emission_records_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `emission_records_ibfk_2` FOREIGN KEY (`energy_activity_id`) REFERENCES `energy_activities` (`id`),
  CONSTRAINT `emission_records_ibfk_3` FOREIGN KEY (`fuel_activity_id`) REFERENCES `fuel_activities` (`id`),
  CONSTRAINT `emission_records_ibfk_4` FOREIGN KEY (`emission_factor_id`) REFERENCES `emission_factors` (`id`),
  CONSTRAINT `fk_emission_records_report_id` FOREIGN KEY (`report_id`) REFERENCES `environmental_topics` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `emission_records` */

insert  into `emission_records`(`id`,`company_id`,`report_id`,`scope`,`tco2e_calculated`,`date_calculated`,`energy_activity_id`,`fuel_activity_id`,`emission_factor_id`) values 
('8989968f-cbf4-4f2e-bda9-7f008d14e3f4','cb319234-ddea-4f85-ba8f-ce7cb81f89e0',NULL,'Scope 2 Location-Based',18.9000,'2026-04-17 11:58:13','fa4a471e-f8b3-490b-8fd9-fdef87f58f9a',NULL,'c7d671ce-2bc8-4b03-921b-92034d20b09f');

/*Table structure for table `energy_activities` */

DROP TABLE IF EXISTS `energy_activities`;

CREATE TABLE `energy_activities` (
  `id` char(36) NOT NULL,
  `site_id` char(36) NOT NULL,
  `date` datetime NOT NULL,
  `energy_type` varchar(100) NOT NULL,
  `consumption` decimal(15,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_energy_activities_site_date` (`site_id`,`date`),
  CONSTRAINT `energy_activities_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `energy_activities` */

insert  into `energy_activities`(`id`,`site_id`,`date`,`energy_type`,`consumption`,`unit`,`created_at`,`updated_at`) values 
('10cb350c-cf7b-4caa-b6fb-578c80460956','70d79177-fd1b-4147-a34e-8f8318dc4845','2026-04-01 00:00:00','electricity',5000.00,'kWh','2026-04-17 11:53:50','2026-04-17 11:53:50'),
('c6ad5788-6a31-47ec-9c52-40eca206c8d8','70d79177-fd1b-4147-a34e-8f8318dc4845','2026-04-01 00:00:00','electricity',65456.00,'kWh','2026-04-17 11:56:09','2026-04-17 11:56:09'),
('fa4a471e-f8b3-490b-8fd9-fdef87f58f9a','70d79177-fd1b-4147-a34e-8f8318dc4845','2026-03-01 00:00:00','district_heating',45.00,'MWh','2026-04-17 11:58:13','2026-04-17 11:58:13');

/*Table structure for table `environmental_topics` */

DROP TABLE IF EXISTS `environmental_topics`;

CREATE TABLE `environmental_topics` (
  `id` char(36) NOT NULL,
  `company_id` char(36) NOT NULL,
  `reporting_period` datetime NOT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `status` enum('DRAFT','UNDER_REVIEW','APPROVED','PUBLISHED','REJECTED') DEFAULT 'DRAFT',
  `e1_material` tinyint(1) DEFAULT 0,
  `e1_climate_policy` text DEFAULT NULL,
  `e1_reduction_target` varchar(255) DEFAULT NULL,
  `e2_nox_t_per_year` decimal(10,2) DEFAULT NULL,
  `e2_sox_t_per_year` decimal(10,2) DEFAULT NULL,
  `e3_water_withdrawal_m3` decimal(15,2) DEFAULT NULL,
  `e3_water_recycling_rate_pct` decimal(5,2) DEFAULT NULL,
  `e4_protected_areas_impact` text DEFAULT NULL,
  `e5_recycling_rate_pct` decimal(5,2) DEFAULT NULL,
  `e5_recycled_input_materials_pct` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_environmental_company_period` (`company_id`,`reporting_period`),
  KEY `idx_environmental_company_id` (`company_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `environmental_topics_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `environmental_topics_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `environmental_topics_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `environmental_topics` */

/*Table structure for table `esrs2_general_disclosures` */

DROP TABLE IF EXISTS `esrs2_general_disclosures`;

CREATE TABLE `esrs2_general_disclosures` (
  `id` char(36) NOT NULL,
  `company_id` char(36) NOT NULL,
  `reporting_period` datetime NOT NULL,
  `consolidation_scope` text NOT NULL,
  `value_chain_boundaries` text NOT NULL,
  `board_role_in_sustainability` text NOT NULL,
  `esg_integration_in_remuneration` int(11) DEFAULT NULL,
  `assessment_process` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_esrs2_company_period` (`company_id`,`reporting_period`),
  KEY `idx_esrs2_company_id` (`company_id`),
  CONSTRAINT `esrs2_general_disclosures_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `esrs2_general_disclosures` */

/*Table structure for table `eu_taxonomy` */

DROP TABLE IF EXISTS `eu_taxonomy`;

CREATE TABLE `eu_taxonomy` (
  `id` char(36) NOT NULL,
  `company_id` char(36) NOT NULL,
  `reporting_period` datetime NOT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `status` enum('DRAFT','UNDER_REVIEW','APPROVED','PUBLISHED','REJECTED') DEFAULT 'DRAFT',
  `economic_activities` text DEFAULT NULL,
  `taxonomy_eligible_revenue_pct` int(11) DEFAULT NULL,
  `taxonomy_aligned_revenue_pct` int(11) DEFAULT NULL,
  `technical_screening_criteria` text DEFAULT NULL,
  `dnsh_status` enum('ALL_OBJECTIVES_PASSED','SOME_OBJECTIVES_NOT_MET','ASSESSMENT_IN_PROGRESS') DEFAULT NULL,
  `social_safeguards_status` enum('FULL_COMPLIANCE','NON_COMPLIANCE','PARTIAL_REMEDIATION') DEFAULT NULL,
  `taxonomy_eligible_capex_pct` int(11) DEFAULT NULL,
  `taxonomy_aligned_capex_pct` int(11) DEFAULT NULL,
  `taxonomy_aligned_opex_pct` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_taxonomy_company_period` (`company_id`,`reporting_period`),
  KEY `idx_taxonomy_company_id` (`company_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `eu_taxonomy_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `eu_taxonomy_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `eu_taxonomy_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `eu_taxonomy` */

/*Table structure for table `fuel_activities` */

DROP TABLE IF EXISTS `fuel_activities`;

CREATE TABLE `fuel_activities` (
  `id` char(36) NOT NULL,
  `site_id` char(36) NOT NULL,
  `date` datetime NOT NULL,
  `fuel_type` varchar(100) NOT NULL,
  `volume` decimal(15,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fuel_activities_site_date` (`site_id`,`date`),
  CONSTRAINT `fuel_activities_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `fuel_activities` */

insert  into `fuel_activities`(`id`,`site_id`,`date`,`fuel_type`,`volume`,`unit`,`created_at`,`updated_at`) values 
('aafde160-8d80-4c59-904b-e0cf586f2869','70d79177-fd1b-4147-a34e-8f8318dc4845','2026-04-15 00:00:00','diesel',500.00,'litre','2026-04-17 11:52:53','2026-04-17 11:52:53');

/*Table structure for table `s_governance` */

DROP TABLE IF EXISTS `s_governance`;

CREATE TABLE `s_governance` (
  `id` char(36) NOT NULL,
  `company_id` char(36) NOT NULL,
  `reporting_period` datetime NOT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `status` enum('DRAFT','UNDER_REVIEW','APPROVED','PUBLISHED','REJECTED') DEFAULT 'DRAFT',
  `g1_board_composition_independence` varchar(255) DEFAULT NULL,
  `g1_gender_diversity_pct` int(11) DEFAULT NULL,
  `g1_esg_oversight` text DEFAULT NULL,
  `g1_whistleblower_cases` text DEFAULT NULL,
  `g1_anti_corruption_policies` varchar(255) DEFAULT NULL,
  `g1_related_party_controls` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_governance_company_period` (`company_id`,`reporting_period`),
  KEY `idx_governance_company_id` (`company_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `s_governance_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `s_governance_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `s_governance_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `s_governance` */

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
  `id` char(36) NOT NULL,
  `company_id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `country` varchar(10) DEFAULT 'DE',
  `created_by` char(36) DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sites_company_name` (`company_id`,`name`),
  KEY `idx_sites_company_id` (`company_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sites_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sites_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `sites` */

insert  into `sites`(`id`,`company_id`,`name`,`address`,`country`,`created_by`,`updated_by`,`deleted_at`,`created_at`,`updated_at`) values 
('70d79177-fd1b-4147-a34e-8f8318dc4845','cb319234-ddea-4f85-ba8f-ce7cb81f89e0','Headquarters - Main Office','123 Business Street, Tech Park, Suite 100','US','b7d66bd1-fcf0-4e8c-8933-c75bc14d008d',NULL,NULL,'2026-04-17 11:52:32','2026-04-17 11:52:32');

/*Table structure for table `social_topics` */

DROP TABLE IF EXISTS `social_topics`;

CREATE TABLE `social_topics` (
  `id` char(36) NOT NULL,
  `company_id` char(36) NOT NULL,
  `reporting_period` datetime NOT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `status` enum('DRAFT','UNDER_REVIEW','APPROVED','PUBLISHED','REJECTED') DEFAULT 'DRAFT',
  `s1_material` tinyint(1) DEFAULT 0,
  `s1_employee_count_by_contract` text DEFAULT NULL,
  `s1_health_and_safety` text DEFAULT NULL,
  `s1_training_hours_per_employee` int(11) DEFAULT NULL,
  `s2_material` tinyint(1) DEFAULT 0,
  `s2_pct_suppliers_audited` int(11) DEFAULT NULL,
  `s2_remediation_actions` text DEFAULT NULL,
  `s3_material` tinyint(1) DEFAULT 0,
  `s3_community_engagement` text DEFAULT NULL,
  `s3_complaints_and_outcomes` varchar(255) DEFAULT NULL,
  `s4_material` tinyint(1) DEFAULT 0,
  `s4_product_safety_incidents` int(11) DEFAULT NULL,
  `s4_consumer_remediation` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_social_company_period` (`company_id`,`reporting_period`),
  KEY `idx_social_company_id` (`company_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `social_topics_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `social_topics_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `social_topics_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `social_topics` */

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` char(36) NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'user',
  `company_id` char(36) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_company_id` (`company_id`),
  KEY `idx_users_email` (`email`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `users` */

insert  into `users`(`id`,`email`,`name`,`password`,`role`,`company_id`,`created_at`,`updated_at`,`deleted_at`) values 
('b7d66bd1-fcf0-4e8c-8933-c75bc14d008d','eh.emon3059@gmail.com','Md Emran Hossain Emon','$2y$10$5WeiKZQGT8T8WOYvhBYevepcXYDLsgjbcp8TCkZWDwS4GgRvW9o.a','admin','cb319234-ddea-4f85-ba8f-ce7cb81f89e0','2026-04-17 11:40:03','2026-04-17 11:40:03',NULL);

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
