# Design Document: File Cleanup and Reorganization

## Overview

The primary objective of this architecture update is the semantic classification and relocation of scripts and files that do not align with their current directories. Specifically, database-level operations will move from the web-facing `admin/` directory to `maintenance/`, and root-level debug artifacts will be stored in `debug/`.

## Scope of File Movements

### Maintenance
Moving database scripts to `maintenance/`.

- Source: `admin/clean_orphans.php`
- Destination: `maintenance/clean_orphans.php`
- Path Correction: `require_once __DIR__ . '/../config/db.php';` (No change required, as both `admin/` and `maintenance/` are one level down from root).

- Source: `admin/fix_database.php`
- Destination: `maintenance/fix_database.php`
- Path Correction: `require_once __DIR__ . '/../config/db.php';` (No change required, for the same reason).

### Debug
Moving root-level output and testing files to `debug/`.

- Source: `testing_output.txt`
- Destination: `debug/testing_output.txt`

## Execution Steps

1. **Move Files**: Use file system operations to relocate the target files.
2. **Refactor Code (if needed)**: While the relative path depth to `config/` remains identical (from `admin/` vs `maintenance/`), it is standard practice to review `require` or `include` statements.
3. **Validate**: Check file structure via shell commands or IDE to confirm the relocations.

## Security and Error Handling

Since some of these files are executable PHP scripts accessible via HTTP (e.g., in a Laragon environment), relocating them to `maintenance/` reduces the clutter in the `admin/` interface. However, both scripts rely on `requireAdmin();`, which already provides adequate application-level protection.

## Correctness Properties

This feature involves standard file I/O operations (relocation) and does not involve complex transformations, pure functions, or algorithms. Thus, Property-Based Testing (PBT) is not applicable. Validation will be performed manually or via integration testing.