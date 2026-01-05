<?php namespace Roline\Commands\Database;

/**
 * DbList Command
 *
 * Lists all databases on the MySQL server with table counts and names.
 *
 * Usage:
 *   php roline db:list
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright 2015 - 2050 Geoffrey Okongo
 * @category Roline
 * @package Roline\Commands\Database
 * @link https://github.com/glivers/roline
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 1.0.0
 */

use Roline\Output;
use Rackage\Registry;

class DbList extends DatabaseCommand
{
    public function description()
    {
        return 'List all databases with table counts';
    }

    public function usage()
    {
        return '';
    }

    public function help()
    {
        parent::help();

        Output::info('Description:');
        Output::line('  Lists all databases on the MySQL server with table counts.');
        Output::line();

        Output::info('Examples:');
        Output::line('  php roline db:list');
        Output::line();
    }

    public function execute($arguments)
    {
        try {
            // Get database configuration
            $dbConfig = Registry::database();
            $driver = $dbConfig['default'] ?? 'mysql';
            $config = $dbConfig[$driver] ?? [];

            $host = $config['host'] ?? 'localhost';
            $username = $config['username'] ?? 'root';
            $password = $config['password'] ?? '';
            $port = $config['port'] ?? 3306;
            $currentDb = $config['database'] ?? '';

            // Connect to MySQL without selecting a database
            $conn = new \mysqli($host, $username, $password, '', $port);

            if ($conn->connect_error) {
                throw new \Exception("Connection failed: " . $conn->connect_error);
            }

            // Get all databases (excluding system databases)
            $result = $conn->query("SHOW DATABASES");

            $this->line();
            $this->info("Databases on {$host}:");
            $this->line();

            $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys'];

            while ($row = $result->fetch_array()) {
                $dbName = $row[0];

                // Skip system databases
                if (in_array($dbName, $systemDbs)) {
                    continue;
                }

                // Get tables for this database
                $tablesResult = $conn->query(
                    "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA = '{$dbName}' ORDER BY TABLE_NAME"
                );

                $tableCount = $tablesResult->num_rows;
                $tableNames = [];
                while ($tbl = $tablesResult->fetch_assoc()) {
                    $tableNames[] = $tbl['TABLE_NAME'];
                }

                // Mark current database
                $marker = ($dbName === $currentDb) ? ' *' : '';

                // Format table list (show first few, truncate if many)
                if ($tableCount === 0) {
                    $tableList = '(empty)';
                } elseif ($tableCount <= 5) {
                    $tableList = implode(', ', $tableNames);
                } else {
                    $shown = array_slice($tableNames, 0, 4);
                    $tableList = implode(', ', $shown) . ", +" . ($tableCount - 4) . " more";
                }

                $this->info("  {$dbName}{$marker}");
                $this->line("    {$tableCount} tables: {$tableList}");
                $this->line();
            }

            if (!empty($currentDb)) {
                $this->line("  * = current database from config");
                $this->line();
            }

            $conn->close();

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            exit(1);
        }
    }
}
