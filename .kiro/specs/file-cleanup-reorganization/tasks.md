# Implementation Plan: File Cleanup and Reorganization

## Overview

This plan details the steps required to move database maintenance scripts from `admin/` to `maintenance/` and relocate root-level debug/testing logs to `debug/`.

## Tasks

- [x] 1. Relocate Database Maintenance Scripts
  - Move `admin/clean_orphans.php` to `maintenance/clean_orphans.php` using smart relocation.
  - Move `admin/fix_database.php` to `maintenance/fix_database.php` using smart relocation.
  - _Requirements: 1.1, 1.2_

- [x] 2. Update and Verify Script Paths
  - Check `maintenance/clean_orphans.php` and verify config path: `require_once __DIR__ . '/../config/db.php'` and `admin-auth.php`.
  - Check `maintenance/fix_database.php` and verify config path.
  - _Requirements: 1.3_

- [x] 3. Relocate Debug and Testing Logs
  - Move `testing_output.txt` to `debug/testing_output.txt`.
  - _Requirements: 2.1_

- [x] 4. Checkpoint - Verify files are moved correctly
  - Verify that the moved files exist in their destinations and are removed from the sources. Ensure no errors occur when executing the scripts if needed.
