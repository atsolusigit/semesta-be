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

        // 1. Drop foreign keys jika ada
        $this->dropForeignKeyIfExists('kategori_risiko_bumn_id');
        $this->dropForeignKeyIfExists('kategori_risiko_t2_t3_kbumn_id');

        // 2. Drop kolom ID
        Schema::table('lost_events', function (Blueprint $table) use ($columns) {
            $columnsToCheck = [
                'kategori_risiko_bumn_id',
                'kategori_risiko_t2_t3_kbumn_id'
            ];

            foreach ($columnsToCheck as $column) {
                if (in_array($column, $columns)) {
                    $table->dropColumn($column);
                }
            }
        });

        // 3. Tambahkan kembali kolom VARCHAR
        Schema::table('lost_events', function (Blueprint $table) use ($columns) {
            if (!in_array('kategori_risiko_bumn', $columns)) {
                $table->string('kategori_risiko_bumn', 255)->nullable()->after('status_asuransi');
            }

            if (!in_array('kategori_risiko_t2_t3_kbumn', $columns)) {
                $table->string('kategori_risiko_t2_t3_kbumn', 255)->nullable()->after('kategori_risiko_bumn');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cek kolom yang ada
        $columns = Schema::getColumnListing('lost_events');

        // 1. Drop kolom VARCHAR
        Schema::table('lost_events', function (Blueprint $table) use ($columns) {
            if (in_array('kategori_risiko_bumn', $columns)) {
                $table->dropColumn('kategori_risiko_bumn');
            }

            if (in_array('kategori_risiko_t2_t3_kbumn', $columns)) {
                $table->dropColumn('kategori_risiko_t2_t3_kbumn');
            }
        });

        // 2. Tambahkan kembali kolom ID
        Schema::table('lost_events', function (Blueprint $table) use ($columns) {
            if (!in_array('kategori_risiko_bumn_id', $columns)) {
                $table->unsignedBigInteger('kategori_risiko_bumn_id')->nullable()->after('status_asuransi');
            }

            if (!in_array('kategori_risiko_t2_t3_kbumn_id', $columns)) {
                $table->unsignedBigInteger('kategori_risiko_t2_t3_kbumn_id')->nullable()->after('kategori_risiko_bumn_id');
            }
        });

        // 3. Tambahkan kembali foreign keys
        $this->addForeignKeyIfNotExists('kategori_risiko_bumn_id', 'kategori_risiko_bumn');
        $this->addForeignKeyIfNotExists('kategori_risiko_t2_t3_kbumn_id', 'kategori_risiko_t2_t3_kbumn');
    }

    /**
     * Helper: Drop foreign key jika ada
     */
    private function dropForeignKeyIfExists($column)
    {
        // Cek apakah kolom ada
        $columns = Schema::getColumnListing('lost_events');
        if (!in_array($column, $columns)) {
            return;
        }

        // Ambil semua foreign keys yang terkait dengan kolom ini
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'lost_events'
            AND COLUMN_NAME = '{$column}'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Drop setiap foreign key yang ditemukan
        if (!empty($foreignKeys)) {
            Schema::table('lost_events', function (Blueprint $table) use ($foreignKeys) {
                foreach ($foreignKeys as $fk) {
                    try {
                        $table->dropForeign($fk->CONSTRAINT_NAME);
                    } catch (\Exception $e) {
                        \Log::warning("Cannot drop FK {$fk->CONSTRAINT_NAME}: " . $e->getMessage());
                    }
                }
            });
        }
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
                \Log::warning("Cannot add FK {$fkName}: " . $e->getMessage());
            }
        }
    }
};
