-- Database Schema Patch: Working Schedule Feature
-- Run this on your OrangeHRM database (e.g. orangehrm)

CREATE TABLE IF NOT EXISTS `ohrm_employee_work_schedule_config` (
  `emp_number` INT NOT NULL,
  `is_customizable` TINYINT(1) DEFAULT 0 NOT NULL,
  PRIMARY KEY (`emp_number`),
  CONSTRAINT `fk_ohrm_emp_work_schedule_config_emp` FOREIGN KEY (`emp_number`) REFERENCES `hs_hr_employee` (`emp_number`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ohrm_work_schedule_request` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `emp_number` INT NOT NULL,
  `work_shift_id` INT NOT NULL,
  `reason` TEXT NULL,
  `status` VARCHAR(20) DEFAULT 'pending' NOT NULL,
  `created_at` DATETIME NOT NULL,
  CONSTRAINT `fk_ohrm_work_schedule_request_emp` FOREIGN KEY (`emp_number`) REFERENCES `hs_hr_employee` (`emp_number`) ON DELETE CASCADE,
  CONSTRAINT `fk_ohrm_work_schedule_request_shift` FOREIGN KEY (`work_shift_id`) REFERENCES `ohrm_work_shift` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
