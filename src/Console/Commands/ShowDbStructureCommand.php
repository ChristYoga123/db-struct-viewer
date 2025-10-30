<?php

namespace Christyoga123\DbStructureViewer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ShowDbStructureCommand extends Command
{
    protected $signature = 'db:structure 
                            {table? : Specific table name to show}
                            {--all : Show all tables structure}
                            {--list : List all available tables}
                            {--migrations : Show related migrations}';

    protected $description = 'Show database structure and related migrations';

    public function handle()
    {
        $table = $this->argument('table');
        $showAll = $this->option('all');
        $listOnly = $this->option('list');
        $showMigrations = $this->option('migrations');

        if ($listOnly) {
            return $this->listTables();
        }

        if ($showAll) {
            return $this->showAllTables($showMigrations);
        }

        if ($table) {
            return $this->showTableStructure($table, $showMigrations);
        }

        return $this->interactiveMode();
    }

    protected function listTables()
    {
        $tables = $this->getAllTables();
        
        $this->info('📋 Available Tables:');
        $this->newLine();
        
        foreach ($tables as $index => $table) {
            $this->line(($index + 1) . '. ' . $table);
        }
        
        $this->newLine();
        $this->info('Total: ' . count($tables) . ' tables');
        
        return 0;
    }

    protected function interactiveMode()
    {
        $this->info('🔍 Database Structure Viewer');
        $this->newLine();

        $choice = $this->choice(
            'What would you like to do?',
            [
                'Show all tables structure',
                'Show specific table structure',
                'List all tables',
            ],
            0
        );

        switch ($choice) {
            case 'Show all tables structure':
                $showMigrations = $this->confirm('Do you want to see related migrations?', true);
                return $this->showAllTables($showMigrations);

            case 'Show specific table structure':
                $tables = $this->getAllTables();
                $table = $this->choice('Select a table:', $tables);
                $showMigrations = $this->confirm('Do you want to see related migrations?', true);
                return $this->showTableStructure($table, $showMigrations);

            case 'List all tables':
                return $this->listTables();
        }

        return 0;
    }

    protected function showAllTables($showMigrations = false)
    {
        $tables = $this->getAllTables();
        
        $this->info('📊 Database Structure - All Tables');
        $this->newLine();

        foreach ($tables as $table) {
            $this->showTableStructure($table, $showMigrations);
            $this->newLine();
            $this->line(str_repeat('─', 80));
            $this->newLine();
        }

        return 0;
    }

    protected function showTableStructure($tableName, $showMigrations = false)
    {
        if (!$this->tableExists($tableName)) {
            $this->error("Table '{$tableName}' does not exist!");
            return 1;
        }

        $this->info("📋 Table: {$tableName}");
        $this->newLine();

        $columns = $this->getTableColumns($tableName);
        
        $this->info('Columns:');
        $columnData = [];
        
        foreach ($columns as $column) {
            $columnData[] = [
                'Name' => $column->Field,
                'Type' => $column->Type,
                'Null' => $column->Null,
                'Key' => $column->Key,
                'Default' => $column->Default ?? 'NULL',
                'Extra' => $column->Extra,
            ];
        }
        
        $this->table(
            ['Name', 'Type', 'Null', 'Key', 'Default', 'Extra'],
            $columnData
        );

        $indexes = $this->getTableIndexes($tableName);
        if (!empty($indexes)) {
            $this->newLine();
            $this->info('Indexes:');
            $indexData = [];
            
            foreach ($indexes as $index) {
                $indexData[] = [
                    'Name' => $index->Key_name,
                    'Column' => $index->Column_name,
                    'Unique' => $index->Non_unique == 0 ? 'Yes' : 'No',
                    'Type' => $index->Index_type,
                ];
            }
            
            $this->table(
                ['Name', 'Column', 'Unique', 'Type'],
                $indexData
            );
        }

        if ($showMigrations) {
            $this->newLine();
            $this->showRelatedMigrations($tableName);
        }

        return 0;
    }

    protected function showRelatedMigrations($tableName)
    {
        $this->info('🔄 Related Migrations:');
        $this->newLine();

        $migrationPath = database_path('migrations');
        
        if (!File::exists($migrationPath)) {
            $this->warn('Migration directory not found!');
            return;
        }

        $migrations = File::allFiles($migrationPath);
        $relatedMigrations = [];

        foreach ($migrations as $migration) {
            $filename = $migration->getFilename();
            $content = File::get($migration->getPathname());
            
            if ($this->migrationReferencesTable($content, $tableName)) {
                $relatedMigrations[] = [
                    'file' => $filename,
                    'path' => $migration->getPathname(),
                    'type' => $this->detectMigrationType($content, $tableName),
                    'content' => $content,
                ];
            }
        }

        if (empty($relatedMigrations)) {
            $this->warn("No migrations found for table '{$tableName}'");
            return;
        }

        // Sort migrations by filename (chronological order)
        usort($relatedMigrations, function($a, $b) {
            return strcmp($a['file'], $b['file']);
        });

        foreach ($relatedMigrations as $index => $migration) {
            $this->line(($index + 1) . '. ' . $migration['file']);
            $this->line('   Type: ' . $migration['type']);
            
            if ($this->option('verbose')) {
                $this->line('   Path: ' . $migration['path']);
            }
        }

        $this->newLine();
        $this->info('Total: ' . count($relatedMigrations) . ' migration(s) found');

        if ($this->confirm('Do you want to see the migration files content?', false)) {
            foreach ($relatedMigrations as $migration) {
                $this->newLine();
                $this->info('📄 ' . $migration['file']);
                $this->line(str_repeat('─', 80));
                $this->line($migration['content']);
            }
        }
    }

    /**
     * Detect migration type based on content analysis
     */
    protected function detectMigrationType($content, $tableName)
    {
        $quoted = preg_quote($tableName, '/');
        
        // Check for Schema::create
        if (preg_match("/Schema\s*::\s*create\s*\(\s*['\"]" . $quoted . "['\"]/i", $content)) {
            return '🆕 Create Table';
        }
        
        // Check for Schema::table (alter/modify)
        if (preg_match("/Schema\s*::\s*table\s*\(\s*['\"]" . $quoted . "['\"]/i", $content)) {
            // Try to detect specific alter type
            if (preg_match('/\$table\s*->\s*drop(Column|Foreign|Index|Primary|Unique)/i', $content)) {
                return '➖ Drop Column/Key';
            }
            if (preg_match('/\$table\s*->\s*rename(Column|Index)/i', $content)) {
                return '🔄 Rename Column/Index';
            }
            if (preg_match('/\$table\s*->\s*(string|integer|text|boolean|date|timestamp|json|decimal|float|bigInteger|foreignId|foreign)\s*\(/i', $content)) {
                return '➕ Add Column(s)';
            }
            if (preg_match('/\$table\s*->\s*(change|modify)/i', $content)) {
                return '✏️ Modify Column(s)';
            }
            return '🔧 Alter Table';
        }
        
        // Check for Schema::dropIfExists or Schema::drop
        if (preg_match("/Schema\s*::\s*(dropIfExists|drop)\s*\(\s*['\"]" . $quoted . "['\"]/i", $content)) {
            return '🗑️ Drop Table';
        }
        
        // Check for Schema::rename
        if (preg_match("/Schema\s*::\s*rename\s*\(\s*['\"]" . $quoted . "['\"]/i", $content)) {
            return '🔄 Rename Table';
        }
        
        // Check for raw SQL
        if (preg_match("/DB\s*::\s*(statement|unprepared)/i", $content)) {
            return '⚙️ Raw SQL';
        }
        
        return '🔧 Other';
    }

    /**
     * Check if migration content references the exact table
     */
    protected function migrationReferencesTable(string $content, string $tableName): bool
    {
        $quoted = preg_quote($tableName, '/');
        
        // Pattern untuk Schema::create
        if (preg_match("/Schema\s*::\s*create\s*\(\s*['\"]" . $quoted . "['\"]\s*,/i", $content)) {
            return true;
        }
        
        // Pattern untuk Schema::table
        if (preg_match("/Schema\s*::\s*table\s*\(\s*['\"]" . $quoted . "['\"]\s*,/i", $content)) {
            return true;
        }
        
        // Pattern untuk Schema::dropIfExists atau Schema::drop
        if (preg_match("/Schema\s*::\s*(dropIfExists|drop)\s*\(\s*['\"]" . $quoted . "['\"][\s,\)]/i", $content)) {
            return true;
        }
        
        // Pattern untuk Schema::rename
        if (preg_match("/Schema\s*::\s*rename\s*\(\s*['\"]" . $quoted . "['\"][\s,]/i", $content)) {
            return true;
        }
        
        // Pattern untuk FQCN Illuminate\Support\Facades\Schema
        if (preg_match("/Illuminate\\\\Support\\\\Facades\\\\Schema\s*::\s*(create|table|dropIfExists|drop|rename)\s*\(\s*['\"]" . $quoted . "['\"][\s,\)]/i", $content)) {
            return true;
        }
        
        // Pattern untuk raw SQL dengan DB facade
        if (preg_match("/DB\s*::\s*(?:statement|unprepared)\s*\(['\"][^'\"]*\b(CREATE|ALTER|DROP)\s+TABLE\s+[`\"]?" . $quoted . "[`\"]?/i", $content)) {
            return true;
        }
        
        // Pattern untuk FQCN DB facade
        if (preg_match("/Illuminate\\\\Support\\\\Facades\\\\DB\s*::\s*(?:statement|unprepared)\s*\(['\"][^'\"]*\b(CREATE|ALTER|DROP)\s+TABLE\s+[`\"]?" . $quoted . "[`\"]?/i", $content)) {
            return true;
        }
        
        return false;
    }

    protected function getAllTables()
    {
        $database = DB::getDatabaseName();
        
        $tables = DB::select("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ", [$database]);
        
        return array_map(fn($table) => $table->TABLE_NAME, $tables);
    }

    protected function tableExists($tableName)
    {
        return in_array($tableName, $this->getAllTables());
    }

    protected function getTableColumns($tableName)
    {
        return DB::select("SHOW COLUMNS FROM {$tableName}");
    }

    protected function getTableIndexes($tableName)
    {
        return DB::select("SHOW INDEXES FROM {$tableName}");
    }
}