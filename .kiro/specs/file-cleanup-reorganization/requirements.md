# Requirements Document

## Introduction

This feature encompasses reorganizing and cleaning up project files. Temporary, debug, maintenance, and query/scratch scripts scattered across directories like the root directory or `admin/` will be moved into structured subdirectories (`debug/` or `maintenance/`) to ensure a clean codebase and ease of maintainability.

## Glossary

- **Debug Directory**: The `debug/` folder in the project root, used for files that help debug, troubleshoot, or log issues.
- **Maintenance Directory**: The `maintenance/` folder in the project root, used for database migrations, structure fixes, and maintenance tasks.
- **Admin Directory**: The `admin/` folder containing administrative UI screens.

## Requirements

### Requirement 1: Database and Maintenance scripts relocation
**User Story:** As a developer, I want database maintenance scripts moved out of the `admin/` folder so that administrative UI folders only contain presentation and route handlers.

#### Acceptance Criteria
1. WHEN the project is reorganized, THE System SHALL relocate `admin/clean_orphans.php` to the `maintenance/` directory.
2. WHEN the project is reorganized, THE System SHALL relocate `admin/fix_database.php` to the `maintenance/` directory.
3. WHEN a script is relocated, THE System SHALL update all relevant database credentials, require, or include paths (e.g. `../config/db.php`) relative to its new location to prevent script failure.

### Requirement 2: Debug and Temporary files relocation
**User Story:** As a developer, I want debug, scratch, and temporary files relocated to appropriate debug/scratch folders to keep the root directory clean.

#### Acceptance Criteria
1. WHEN the project is reorganized, THE System SHALL relocate `testing_output.txt` from the root directory to the `debug/` directory.
2. WHEN the project is reorganized, THE System SHALL ensure all other temporary or log files in the project root are moved to the `debug/` directory.
3. WHEN the project is reorganized, THE System SHALL relocate scratch files (like `scratch/query_order.php`) if they belong in debug or clean them up.
