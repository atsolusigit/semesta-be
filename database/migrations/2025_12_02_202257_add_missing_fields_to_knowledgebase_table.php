<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('knowledgebase', function (Blueprint $table) {
            // Cek dan tambah kolom yang belum ada
            if (!Schema::hasColumn('knowledgebase', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('creator_id');
            }

            if (!Schema::hasColumn('knowledgebase', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }

            if (!Schema::hasColumn('knowledgebase', 'title')) {
                $table->string('title', 255)->nullable()->after('updated_by');
            }

            if (!Schema::hasColumn('knowledgebase', 'doc_path')) {
                $table->longText('doc_path')->nullable()->after('img_path');
            }
        });

        // Ubah img_path menjadi longText dan nullable
        DB::statement('ALTER TABLE knowledgebase MODIFY img_path LONGTEXT NULL');

        // Tambahkan foreign key dengan pengecekan
        $this->addForeignKeyIfNotExists('knowledgebase', 'created_by', 'users', 'knowledgebase_created_by_foreign');
        $this->addForeignKeyIfNotExists('knowledgebase', 'updated_by', 'users', 'knowledgebase_updated_by_foreign');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledgebase', function (Blueprint $table) {
            // Drop foreign keys jika ada
            $this->dropForeignKeyIfExists('knowledgebase', 'knowledgebase_created_by_foreign');
            $this->dropForeignKeyIfExists('knowledgebase', 'knowledgebase_updated_by_foreign');

            // Drop columns
            if (Schema::hasColumn('knowledgebase', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('knowledgebase', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('knowledgebase', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('knowledgebase', 'doc_path')) {
                $table->dropColumn('doc_path');
            }
        });
    }

    /**
     * Helper: Tambah foreign key jika belum ada
     */
    private function addForeignKeyIfNotExists($table, $column, $referencedTable, $constraintName)
    {
        $exists = DB::select(
            "SELECT CONSTRAINT_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND CONSTRAINT_NAME = ?",
            [$table, $constraintName]
        );

        if (empty($exists)) {
            DB::statement(
                "ALTER TABLE `{$table}`
                 ADD CONSTRAINT `{$constraintName}`
                 FOREIGN KEY (`{$column}`)
                 REFERENCES `{$referencedTable}`(`id`)
                 ON DELETE SET NULL"
            );
        }
    }

    /**
     * Helper: Drop foreign key jika ada
     */
    private function dropForeignKeyIfExists($table, $constraintName)
    {
        $exists = DB::select(
            "SELECT CONSTRAINT_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND CONSTRAINT_NAME = ?",
            [$table, $constraintName]
        );

        if (!empty($exists)) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
        }
    }
};
