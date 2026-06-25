<?php

use App\Models\Module;
use App\Models\SubModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_role_sub_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_module_id')->constrained('sys_sub_modules')->cascadeOnDelete();
            $table->boolean('can_add')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('full_control')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'sub_module_id']);
        });

        $this->migrateParentModulePermissionsToSubModules();
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_role_sub_modules');
    }

    private function migrateParentModulePermissionsToSubModules(): void
    {
        $parentModules = Module::query()
            ->whereHas('subModules')
            ->with('subModules')
            ->get();

        foreach ($parentModules as $module) {
            $parentPermissions = DB::table('tbl_role_modules')
                ->where('module_id', $module->id)
                ->get();

            foreach ($parentPermissions as $permission) {
                foreach ($module->subModules as $subModule) {
                    DB::table('tbl_role_sub_modules')->updateOrInsert(
                        [
                            'role_id' => $permission->role_id,
                            'sub_module_id' => $subModule->id,
                        ],
                        [
                            'can_add' => $permission->can_add,
                            'can_edit' => $permission->can_edit,
                            'can_update' => $permission->can_update,
                            'can_delete' => $permission->can_delete,
                            'full_control' => $permission->full_control,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }

            DB::table('tbl_role_modules')->where('module_id', $module->id)->delete();
        }
    }
};
