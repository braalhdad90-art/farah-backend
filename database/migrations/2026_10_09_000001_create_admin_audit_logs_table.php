<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_role', 80)->nullable();
            $table->string('action', 120);
            $table->string('resource_type', 120)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['resource_type', 'resource_id']);
            $table->index(['actor_id', 'created_at']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER admin_audit_logs_no_update BEFORE UPDATE ON admin_audit_logs BEGIN SELECT RAISE(ABORT, 'Admin audit logs are immutable'); END");
            DB::statement("CREATE TRIGGER admin_audit_logs_no_delete BEFORE DELETE ON admin_audit_logs BEGIN SELECT RAISE(ABORT, 'Admin audit logs are immutable'); END");
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER admin_audit_logs_no_update BEFORE UPDATE ON admin_audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Admin audit logs are immutable'");
            DB::unprepared("CREATE TRIGGER admin_audit_logs_no_delete BEFORE DELETE ON admin_audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Admin audit logs are immutable'");
        } elseif ($driver === 'pgsql') {
            DB::unprepared("CREATE FUNCTION reject_admin_audit_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'Admin audit logs are immutable'; END; $$ LANGUAGE plpgsql");
            DB::unprepared('CREATE TRIGGER admin_audit_logs_no_update BEFORE UPDATE OR DELETE ON admin_audit_logs FOR EACH ROW EXECUTE FUNCTION reject_admin_audit_mutation()');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite' || $driver === 'mysql') {
            DB::statement('DROP TRIGGER IF EXISTS admin_audit_logs_no_update');
            DB::statement('DROP TRIGGER IF EXISTS admin_audit_logs_no_delete');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS admin_audit_logs_no_update ON admin_audit_logs');
            DB::statement('DROP FUNCTION IF EXISTS reject_admin_audit_mutation()');
        }

        Schema::dropIfExists('admin_audit_logs');
    }
};
