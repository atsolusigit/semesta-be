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
        // Cek kolom yang ada saat ini
        $columns = Schema::getColumnListing('lost_events');

        // 1. Drop kolom VARCHAR jika masih ada
        Schema::table('lost_events', function (Blueprint $table) use ($columns) {
            $columnsToCheck = [
                'risk_owner_department',
                'jenis_risiko',
                'kategori_risiko_bumn',
                'kategori_risiko_t2_t3_kbumn'
            ];

            foreach ($columnsToCheck as $column) {
                if (in_array($column, $columns)) {
                    // Cek tipe data kolom
                    $columnType = DB::select("
                        SELECT DATA_TYPE
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'lost_events'
                        AND COLUMN_NAME = '{$column}'
                    ");

                    // Hanya drop jika tipe data VARCHAR
                    if (!empty($columnType) && strtolower($columnType[0]->DATA_TYPE) === 'varchar') {
                        $table->dropColumn($column);
                    }
                }
            }
        });

        // 2. Tambah kolom ID jika belum ada
        Schema::table('lost_events', function (Blueprint $table) use ($columns) {
            // risk_owner_department_id
            if (!in_array('risk_owner_department_id', $columns)) {
                $table->unsignedBigInteger('risk_owner_department_id')->nullable()->after('tahun');
            }

            // jenis_risiko_id
            if (!in_array('jenis_risiko_id', $columns)) {
                $table->unsignedBigInteger('jenis_risiko_id')->nullable()->after('risk_owner_department_id');
            }

            // kategori_risiko_bumn_id
            if (!in_array('kategori_risiko_bumn_id', $columns)) {
                $table->unsignedBigInteger('kategori_risiko_bumn_id')->nullable()->after('status_asuransi');
            }

            // kategori_risiko_t2_t3_kbumn_id
            if (!in_array('kategori_risiko_t2_t3_kbumn_id', $columns)) {
                $table->unsignedBigInteger('kategori_risiko_t2_t3_kbumn_id')->nullable()->after('kategori_risiko_bumn_id');
            }
        });

        // 3. Tambah foreign keys
        $this->addForeignKeyIfNotExists('risk_owner_department_id', 'departments');
        $this->addForeignKeyIfNotExists('jenis_risiko_id', 'jenis_risiko');
        $this->addForeignKeyIfNotExists('kategori_risiko_bumn_id', 'kategori_risiko_bumn');
        $this->addForeignKeyIfNotExists('kategori_risiko_t2_t3_kbumn_id', 'kategori_risiko_t2_t3_kbumn');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys
        Schema::table('lost_events', function (Blueprint $table) {
            $foreignKeys = [
                'lost_events_risk_owner_department_id_foreign',
                'lost_events_jenis_risiko_id_foreign',
                'lost_events_kategori_risiko_bumn_id_foreign',
                'lost_events_kategori_risiko_t2_t3_kbumn_id_foreign'
            ];

            foreach ($foreignKeys as $fk) {
                try {
                    $table->dropForeign($fk);
                } catch (\Exception $e) {
                    // Foreign key tidak ada
                }
            }
        });

        // Drop kolom ID
        Schema::table('lost_events', function (Blueprint $table) {
            $columns = Schema::getColumnListing('lost_events');

            $columnsToCheck = [
                'risk_owner_department_id',
                'jenis_risiko_id',
                'kategori_risiko_bumn_id',
                'kategori_risiko_t2_t3_kbumn_id'
            ];

            foreach ($columnsToCheck as $column) {
                if (in_array($column, $columns)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Kembalikan kolom VARCHAR
        Schema::table('lost_events', function (Blueprint $table) {
            $table->string('risk_owner_department', 255)->nullable()->after('tahun');
            $table->string('jenis_risiko', 255)->nullable()->after('risk_owner_department');
            $table->string('kategori_risiko_bumn', 255)->nullable()->after('status_asuransi');
            $table->string('kategori_risiko_t2_t3_kbumn', 255)->nullable()->after('kategori_risiko_bumn');
        });
    }

    /**
     * Helper: Tambah foreign key jika belum ada
     */
    private function addForeignKeyIfNotExists($column, $referencedTable)
    {
        $fkName = "lost_events_{$column}_foreign";

        // Cek apakah kolom ada
        $columns = Schema::getColumnListing('lost_events');
        if (!in_array($column, $columns)) {
            return;
        }

        // Cek apakah foreign key sudah ada
        $exists = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'lost_events'
            AND CONSTRAINT_NAME = '{$fkName}'
        ");

        if (empty($exists)) {
            try {
                Schema::table('lost_events', function (Blueprint $table) use ($column, $referencedTable, $fkName) {
                    $table->foreign($column, $fkName)
                          ->references('id')
                          ->on($referencedTable)
                          ->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Log error tapi jangan stop migration
                \Log::warning("Cannot add FK {$fkName}: " . $e->getMessage());
            }
        }
    }
};
