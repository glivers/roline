# Roline - Rachie Command Line Interface

> Powerful CLI tool for Rachie PHP framework - Generate code, manage databases, and boost productivity.

[![Latest Version](https://img.shields.io/badge/version-1.0-blue)]
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-purple)]
[![License](https://img.shields.io/badge/license-MIT-green)]

## What is Roline?

Roline is the official command-line interface for the Rachie framework. It provides powerful code generation, database management, and migration tools to dramatically speed up your development workflow.

**Think of it as Rachie's version of:**
- Laravel's Artisan
- Symfony's Console
- Rails' rake

**Built on the Command Pattern** with smart command discovery, interactive prompts, and beautiful colored output.

---

## Features

- 🎯 **Smart Command Discovery** - Partial matching with helpful suggestions
- 🎨 **Beautiful Output** - Color-coded messages and formatted help
- 🤖 **Code Generation** - Controllers, models, views in seconds
- 📝 **Annotation-Based Schemas** - Define database structure in models with `@column` tags
- 🔄 **Auto-Migrations** - Generate migrations by comparing database states
- 💾 **Database Management** - Create, update, export, and seed tables
- ⚡ **Interactive Prompts** - Guided workflows for common tasks
- 🛠️ **Extensible** - Easy to add custom commands

---

## Installation

Roline comes bundled with Rachie framework:

```bash
composer require glivers/roline
```

Or if you're starting a new Rachie project:

```bash
composer create-project glivers/rachie myapp
cd myapp
```

---

## Usage

### Basic Syntax

```bash
php roline <command> [arguments] [options]
php roline <command> --help
```

### List All Commands

```bash
php roline list
php roline
```

### Get Help

```bash
php roline --help
php roline controller:create --help
php roline migration:make --help
```

### Smart Command Discovery

Type partial commands to see related options:

```bash
php roline controller
# Shows: controller:create, controller:append, controller:delete, controller:complete

php roline model
# Shows: model:create, model:append, model:create-table, model:update-table, etc.
```

---

## Command Reference

### 🎮 Controller Commands

#### Create Controller

```bash
php roline controller:create Posts
```

**Creates:** `application/controllers/PostsController.php`

**Generated structure:**
```php
<?php namespace Controllers;

use Rackage\Controller;
use Rackage\View;

class PostsController extends Controller
{
    public function getIndex()
    {
        // Your code here
    }
}
```

#### Append Method to Controller

```bash
php roline controller:append Posts getShow
```

Adds a new method to existing controller.

#### Delete Controller

```bash
php roline controller:delete Posts
```

Deletes the controller file after confirmation.

#### Complete Scaffold

```bash
php roline controller:complete Posts
```

Creates controller + model + views in one command (full scaffold).

---

### 📦 Model Commands

#### Create Model

```bash
php roline model:create Post
```

**Interactive prompts:**
- Model name (auto-adds "Model" suffix)
- Table name (auto-pluralized)
- Add properties? (yes/no)
- Property name and type

**Creates:** `application/models/PostModel.php`

**Generated model:**
```php
<?php namespace Models;

use Rackage\Model;

class PostModel extends Model
{
    protected static $table = 'posts';
    protected static $timestamps = true;

    /**
     * @column
     * @primary
     * @autonumber
     */
    protected $id;

    /**
     * @column
     * @datetime
     */
    protected $date_created;

    /**
     * @column
     * @datetime
     */
    protected $date_modified;
}
```

#### Add Properties to Model

```bash
php roline model:append Post
```

Interactively add `@column` properties to existing model.

**Example annotations:**

```php
/**
 * @column
 * @varchar 255
 * @unique
 */
protected $email;

/**
 * @column
 * @text
 * @nullable
 */
protected $bio;

/**
 * @column
 * @enum active,inactive,banned
 * @default active
 */
protected $status;

/**
 * @column
 * @decimal 10,2
 * @unsigned
 */
protected $price;

/**
 * @column
 * @int 11
 * @unsigned
 * @index
 */
protected $user_id;

/**
 * @column
 * @json
 */
protected $metadata;

/**
 * @column
 * @boolean
 * @default 0
 */
protected $is_featured;

/**
 * @column
 * @datetime
 */
protected $published_at;
```

**Supported Types:**
- **Numeric:** `@int`, `@bigint`, `@decimal`, `@float`, `@double`, `@tinyint`, `@smallint`, `@mediumint`
- **String:** `@varchar`, `@char`, `@text`, `@mediumtext`, `@longtext`
- **Date/Time:** `@datetime`, `@date`, `@time`, `@timestamp`, `@year`
- **Special:** `@enum`, `@set`, `@boolean`, `@bool`, `@json`, `@autonumber`, `@uuid`
- **Binary:** `@blob`, `@mediumblob`, `@longblob`
- **Spatial:** `@point`, `@geometry`, `@linestring`, `@polygon`

**Supported Modifiers:**
- `@primary` - Primary key
- `@unique` - Unique constraint
- `@nullable` - Allow NULL values
- `@unsigned` - Unsigned numbers only (for numeric types)
- `@default value` - Default value
- `@index` - Add index for faster queries
- `@drop` - Mark column for deletion (use with `model:update-table`)
- `@rename old_name` - Rename from old column name (use with `model:update-table`)
- `@foreign table(column)` - Create foreign key constraint
- `@ondelete ACTION` - ON DELETE action (CASCADE, RESTRICT, SET NULL, NO ACTION)
- `@onupdate ACTION` - ON UPDATE action (CASCADE, RESTRICT, SET NULL, NO ACTION)

**Foreign Key Relationships:**

Define relationships between tables using foreign key constraints. Foreign keys enforce referential integrity and define cascading actions.

**Basic Example:**
```php
/**
 * Foreign key to users table
 * @column
 * @int 11
 * @unsigned
 * @index
 * @foreign users(id)
 * @ondelete CASCADE
 * @onupdate CASCADE
 */
protected $user_id;
```

**Important:** Foreign key column **must** have the exact same data type as the referenced column.

**Referential Actions Explained:**

**ON DELETE** - What happens when parent record is deleted:
- `CASCADE` - Automatically delete all child records (e.g., delete order → delete order items)
- `RESTRICT` - Prevent deletion if child records exist (must delete children first)
- `SET NULL` - Set foreign key to NULL in child records (requires `@nullable`)
- `NO ACTION` - Same as RESTRICT in MySQL

**ON UPDATE** - What happens when parent's primary key is updated:
- `CASCADE` - Automatically update foreign keys in child records (recommended)
- `RESTRICT` - Prevent update if child records exist
- `SET NULL` - Set foreign key to NULL in child records
- `NO ACTION` - Same as RESTRICT in MySQL

**Real-World Examples:**

E-commerce (delete order → delete order items):
```php
/**
 * @column
 * @int 11
 * @unsigned
 * @foreign orders(id)
 * @ondelete CASCADE
 * @onupdate CASCADE
 */
protected $order_id;
```

Blog (delete user → posts become "anonymous"):
```php
/**
 * @column
 * @int 11
 * @unsigned
 * @nullable
 * @foreign users(id)
 * @ondelete SET NULL
 * @onupdate CASCADE
 */
protected $author_id;
```

Inventory (prevent deleting products with stock):
```php
/**
 * @column
 * @int 11
 * @unsigned
 * @foreign products(id)
 * @ondelete RESTRICT
 * @onupdate CASCADE
 */
protected $product_id;
```

#### Create Table from Model

```bash
php roline model:create-table Post
```

Reads `@column` annotations from `PostModel` and generates `CREATE TABLE` SQL.

**What it does:**
1. Parses all `@column` annotated properties
2. Generates CREATE TABLE statement
3. Executes SQL to create table
4. Confirms success

#### Update Table from Model

```bash
php roline model:update-table Post
```

Updates existing table structure based on model changes.

**Detects:**
- New columns added
- Columns removed (marked with `@drop`)
- Columns renamed (marked with `@rename`)
- Column type changes
- Index changes

#### Other Model/Table Commands

```bash
php roline model:drop-table Post                    # Drop table
php roline model:rename-table Post Article          # Rename posts → articles
php roline model:table-schema Post                  # Show table structure
php roline model:empty-table Post                   # DELETE all rows (with confirmation)
php roline model:export-table Post backup.sql       # Export to SQL
php roline model:export-table Post data.csv         # Export to CSV
```

---

### 🎨 View Commands

#### Create View Directory

```bash
php roline view:create posts
```

**Creates:** `application/views/posts/` directory

#### Add View File

```bash
php roline view:add posts index
```

**Creates:** `application/views/posts/index.php`

#### Delete View Directory

```bash
php roline view:delete posts
```

Deletes entire view directory (with confirmation).

---

### 🗄️ Table Commands

Direct database table operations **without** requiring models.

#### Create Table

```bash
php roline table:create posts
```

Creates table interactively or from SQL file:

```bash
php roline table:create posts --sql=schema.sql
```

#### Other Table Commands

```bash
php roline table:delete posts                       # Drop table
php roline table:rename posts articles              # Rename table
php roline table:schema posts                       # Show structure
php roline table:export posts backup.sql            # Export data
```

---

### 🔄 Migration Commands

Version-controlled database changes with automatic migration generation.

#### How Migrations Work

1. **Make changes** to your database (via `model:create-table`, `model:update-table`, or manual SQL)
2. **Generate migration** - Roline compares current schema vs last snapshot
3. **Run migration** on other environments (staging, production)

#### Make Migration

```bash
php roline migration:make add_email_to_users
```

**What happens:**
1. Reads current database schema (all tables, columns, indexes)
2. Loads last schema snapshot from `application/database/schemas/`
3. Compares schemas using `SchemaDiffer`
4. Generates UP SQL (applies changes) and DOWN SQL (reverts changes)
5. Creates timestamped migration file
6. Saves new schema snapshot

**Creates:**
- `application/database/migrations/2024_01_15_143022_add_email_to_users.php`
- `application/database/schemas/2024_01_15_143022_add_email_to_users.json`

**Generated migration file:**
```php
<?php

use Rackage\Database\Connection;

/**
 * Migration: Add Email To Users
 * Generated: 2024-01-15 14:30:22
 */

class AddEmailToUsers
{
    /**
     * Run the migration (apply changes)
     */
    public function up()
    {
        $db = Connection::getInstance();

        $db->execute("
            ALTER TABLE users
            ADD COLUMN email VARCHAR(255) NULL,
            ADD UNIQUE INDEX idx_email (email)
        ");
    }

    /**
     * Rollback the migration (revert changes)
     */
    public function down()
    {
        $db = Connection::getInstance();

        $db->execute("
            ALTER TABLE users
            DROP INDEX idx_email,
            DROP COLUMN email
        ");
    }
}
```

#### Run Migrations

```bash
php roline migration:run
```

Runs all pending migrations in order.

**Migration tracking:**
- Tracks ran migrations in `migrations` database table
- Batches migrations for easy rollback
- Skips already-run migrations

#### Rollback Migrations

```bash
php roline migration:rollback          # Rollback last batch
php roline migration:rollback 2        # Rollback 2 batches
```

#### Migration Status

```bash
php roline migration:status
```

Shows which migrations have been run and which are pending.

---

### 💾 Database Commands

#### Seed Database

```bash
php roline db:seed
php roline db:seed UsersSeeder
```

Runs database seeders from `application/database/seeders/`.

#### Show Database Schema

```bash
php roline db:schema
```

Displays complete database schema (all tables, columns, indexes).

#### Export Database

```bash
php roline db:export backup.sql
php roline db:export backup.csv
```

Exports entire database to SQL or CSV format.

#### Drop All Tables

```bash
php roline db:drop
```

**DANGER:** Drops all database tables. Requires confirmation.

---

### 🧹 Cleanup Commands

#### Clear Cache

```bash
php roline cleanup:cache
```

Clears `vault/cache/` directory.

#### Clear Compiled Views

```bash
php roline cleanup:views
```

Clears `vault/tmp/` (compiled view templates).

#### Clear Logs

```bash
php roline cleanup:logs
```

Clears `vault/logs/error.log`.

#### Clear Sessions

```bash
php roline cleanup:sessions
```

Clears old session files from `vault/sessions/`.

#### Clear Everything

```bash
php roline cleanup:all
```

Runs all cleanup operations (cache + views + logs + sessions).

---

### 🛠️ Utility Commands

```bash
php roline list                    # List all commands
php roline help                    # General help
php roline version                 # Show version
php roline --version
php roline -v
```

---

## Real-World Workflows

### Building a Blog

```bash
# 1. Create model with schema
php roline model:create Post
# → Add properties: title (varchar 255), slug (varchar 255, unique),
#                   body (text), status (enum: draft,published)

# 2. Create table from model
php roline model:create-table Post

# 3. Generate controller
php roline controller:create Posts

# 4. Create views
php roline view:create posts
php roline view:add posts index
php roline view:add posts show
php roline view:add posts create

# 5. Generate migration (for version control)
php roline migration:make create_posts_table
```

### Adding a Feature to Existing Project

```bash
# 1. Add new column to model
php roline model:append User
# → Add: email_verified_at (datetime, nullable)

# 2. Update table
php roline model:update-table User

# 3. Generate migration
php roline migration:make add_email_verification_to_users

# 4. On production server
php roline migration:run
```

### Database Backup & Restore

```bash
# Backup
php roline db:export backup_2024_01_15.sql

# Or backup specific table
php roline model:export-table Users users_backup.sql

# Check schema
php roline db:schema
php roline model:table-schema Users
```

### Cleanup Before Deployment

```bash
php roline cleanup:all
php roline migration:status  # Verify migrations
```

---

## Custom Stubs (Templates)

You can customize code generation templates by creating your own stubs:

**Custom stub locations:**
- `application/database/stubs/model.stub`
- `application/database/stubs/controller.stub`
- `application/database/stubs/migration.stub`

**Example custom model stub:**

```php
<?php namespace Models;

use Rackage\Model;

/**
 * {{ModelName}} Model
 *
 * @author Your Name
 * @created {{Timestamp}}
 */
class {{ModelName}}Model extends Model
{
    protected static $table = '{{TableName}}';
    protected static $timestamps = true;

    // Your custom additions here
}
```

Roline will use your custom stubs instead of the defaults.

---

## Creating Custom Commands

Extend Roline with your own commands:

### 1. Create Command Class

```php
<?php namespace Roline\Commands\Custom;

use Roline\Command;

class MyCustomCommand extends Command
{
    public function description()
    {
        return 'Does something awesome';
    }

    public function usage()
    {
        return '<name|required> [--force]';
    }

    public function execute($arguments)
    {
        // Validate arguments
        if (empty($arguments[0])) {
            $this->error('Name argument is required!');
            exit(1);
        }

        $name = $arguments[0];
        $force = in_array('--force', $arguments);

        // Your command logic here
        $this->info("Processing {$name}...");

        // Use helper methods
        if ($this->confirm("Are you sure?")) {
            $this->success("Done!");
        } else {
            $this->error("Cancelled");
        }
    }

    public function help()
    {
        parent::help();

        // Add custom help sections
        $this->info('Examples:');
        $this->line('  php roline custom:my-command test');
        $this->line('  php roline custom:my-command test --force');
        $this->line();
    }
}
```

### 2. Register Command

In `vendor/glivers/roline/src/Roline.php`, add to `registerCommands()`:

```php
private function registerCommands()
{
    $this->commands = [
        // ... existing commands ...

        'custom:my-command' => Commands\Custom\MyCustomCommand::class,
    ];
}
```

### 3. Use Helper Methods

Available in all commands:

```php
// Output
$this->success('Success message');   // ✓ Green
$this->error('Error message');       // ✗ Red
$this->info('Info message');         // → Yellow
$this->line('Plain text');           // No formatting
$this->line();                       // Blank line

// User interaction
$name = $this->ask('Enter name');
$confirmed = $this->confirm('Delete this?');
```

---

## Architecture

### Command Pattern

Roline uses the **Command Pattern** with:
- **`Roline`** - Command registry and dispatcher
- **`Command`** - Abstract base class (template method pattern)
- **`Output`** - Static helper for formatted output

### Class Hierarchy

```
Command (abstract)
  ├── Controller\ControllerCreate
  ├── Controller\ControllerAppend
  ├── Model\ModelCreate
  ├── Model\ModelCreateTable
  ├── Migration\MigrationMake
  ├── Migration\MigrationRun
  └── ... etc
```

### Key Classes

**`Roline\Roline`**
- Command routing and discovery
- Smart partial matching
- Help flag handling
- Exception handling

**`Roline\Command`** (abstract)
- Base class for all commands
- Template method pattern
- Helper methods (success, error, info, ask, confirm)

**`Roline\Output`**
- Color-coded terminal output
- User input prompts
- ANSI color codes

**`Roline\Utils\ModelParser`**
- Parses `@column` annotations
- Extracts schema from models

**`Roline\Utils\SchemaReader`**
- Reads database schema
- Gets tables, columns, indexes

**`Roline\Utils\SchemaDiffer`**
- Compares two schemas
- Generates UP/DOWN SQL

**`Roline\Utils\Migration`**
- Migration runner
- Tracks ran migrations
- Batch management

---

## Requirements

- PHP 7.4 or higher
- Rachie Framework 2.0+
- Rackage 2.0+
- PDO extension
- MySQL, PostgreSQL, or SQLite

---

## Testing

Roline includes PHPUnit tests:

```bash
# Run all tests
php phpunit.phar

# Run specific test
php phpunit.phar --filter=ModelCreateTest

# With coverage
php phpunit.phar --coverage-html coverage/
```

Tests are located in `tests/` directory.

---

## Contributing

Contributions are welcome! To contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Make your changes
5. Run tests (`php phpunit.phar`)
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

### Coding Standards

- Follow PSR-4 autoloading
- Use descriptive variable/method names
- Add docblocks to all methods
- Keep commands focused (single responsibility)
- Use the provided helper methods (success, error, info, etc.)

---

## Troubleshooting

### Command not found

```bash
php roline list  # Check if command exists
php roline model  # See related commands
```

### Database connection errors

Check `config/database.php` configuration.

### Migration errors

```bash
php roline migration:status  # Check migration state
php roline db:schema         # Verify database schema
```

### Stub file not found

Ensure vendor directory exists:
```bash
composer install
```

Or create custom stub in `application/database/stubs/`.

---

## Changelog

### Version 1.0.0
- Initial release
- Controller, Model, View commands
- Migration system with auto-generation
- Database management commands
- Annotation-based schema system
- Interactive prompts
- Smart command discovery

---

## License

Roline is open-source software licensed under the [MIT license](LICENSE).

---

## Credits

**Created by:** [Geoffrey Okongo](https://github.com/glivers)

**Part of the Rachie ecosystem:**
- [Rachie Framework](https://github.com/glivers/rachie) - Main framework
- [Rackage](https://github.com/glivers/rackage) - Framework engine
- [Roline](https://github.com/glivers/roline) - CLI tool (this package)

---

## Support

- **Email:** code@rachie.dev
- **Issues:** [GitHub Issues](https://github.com/glivers/roline/issues)
- **Documentation:** See CLAUDE.md in Rachie root for framework docs

---

**Make development faster with Roline! ⚡**
