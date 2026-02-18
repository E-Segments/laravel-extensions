---
layout: cli
title: CLI Reference
description: "Command-line interface for the Laravel Extensions package"
cli_name: artisan
cli_version: "1.0.0"
cli_install: composer require esegments/laravel-extensions

commands:
  - name: "extension:list"
    description: "List all registered extension points and handlers"
    options:
      - name: "--point"
        description: "Filter by extension point class name"
      - name: "--handler"
        description: "Filter by handler class name"
      - name: "--unused"
        description: "Show only extension points with no handlers"
      - name: "--tag"
        description: "Filter by handler tag"
      - name: "--group"
        description: "Filter by handler group"
    examples:
      - command: "php artisan extension:list"
        description: "List all extension points and handlers"
      - command: "php artisan extension:list --point=UserCreated"
        description: "Filter by extension point"
      - command: "php artisan extension:list --handler=AuditHandler"
        description: "Filter by handler"
      - command: "php artisan extension:list --unused"
        description: "Show unused extension points"
      - command: "php artisan extension:list --tag=audit"
        description: "Filter by tag"

  - name: "extension:inspect"
    description: "Inspect a specific extension point in detail"
    arguments:
      - name: "extension"
        description: "The extension point class name (full or partial)"
        required: true
    examples:
      - command: "php artisan extension:inspect UserCreated"
        description: "Inspect UserCreated extension point"
      - command: 'php artisan extension:inspect "App\\Extensions\\UserCreated"'
        description: "Inspect with full namespace"

  - name: "extension:stats"
    description: "Show extension execution statistics"
    options:
      - name: "--point"
        description: "Filter by extension point"
    examples:
      - command: "php artisan extension:stats"
        description: "Show all statistics"
      - command: "php artisan extension:stats --point=UserCreated"
        description: "Show stats for specific point"

  - name: "extension:test"
    description: "Test an extension point by dispatching a mock event"
    arguments:
      - name: "extension"
        description: "The extension point to test"
        required: true
    examples:
      - command: "php artisan extension:test UserCreated"
        description: "Test UserCreated handlers"

  - name: "extension:cache"
    description: "Cache extension point discovery"
    examples:
      - command: "php artisan extension:cache"
        description: "Cache handler discovery"

  - name: "extension:clear"
    description: "Clear extension cache"
    examples:
      - command: "php artisan extension:clear"
        description: "Clear handler cache"

  - name: "extension:ide-helper"
    description: "Generate IDE helper files for extensions"
    options:
      - name: "--output"
        description: "Output file path"
    examples:
      - command: "php artisan extension:ide-helper"
        description: "Generate IDE helper"
      - command: "php artisan extension:ide-helper --output=_ide_helper_extensions.php"
        description: "Custom output path"
---

## Global Options

All commands support these global options:

| Option | Description |
|--------|-------------|
| `-h, --help` | Display help for the command |
| `-q, --quiet` | Do not output any message |
| `-V, --version` | Display application version |
| `--ansi` | Force ANSI output |
| `-n, --no-interaction` | Do not ask any interactive question |
| `-v, -vv, -vvv` | Increase verbosity level |

## Understanding Output

### Extension List Output

The `extension:list` command shows a table with:

| Column | Description |
|--------|-------------|
| Extension Point | The fully qualified class name |
| Handler | Handler class or "Closure" |
| Priority | Execution order (higher = first) |
| Tags | Handler tags for filtering |

### Stats Output

The `extension:stats` command displays:

- **Summary**: Total extension points, handlers, groups, and tags
- **Circuit Breaker Status**: Shows handlers with failures
- **Handler Groups**: Group status (enabled/disabled)
- **Handler Tags**: Tag counts and status

### Inspect Output

The `extension:inspect` command shows:

- **Contracts**: What interfaces the extension point implements
- **Properties**: Constructor parameters
- **Handlers**: All registered handlers with their priority, tags, type (async), and circuit breaker status

## Circuit Breaker States

| State | Color | Description |
|-------|-------|-------------|
| Closed | Green | Normal operation |
| Open | Red | Handler disabled due to failures |
| Half-Open | Yellow | Testing if handler recovered |

## Quick Start

```bash
# List all extension points
php artisan extension:list

# Check system health
php artisan extension:stats

# Inspect specific extension
php artisan extension:inspect UserCreated

# Cache for production
php artisan extension:cache
```
