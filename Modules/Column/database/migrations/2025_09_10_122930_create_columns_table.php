<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Column\Models\Column;
use Illuminate\Support\Facades\DB;
use Modules\Menu\Models\Menu;
class CreateColumnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $module = new Column();
        $menu = new Menu();
        $menuId = Menu::where('link','admin.customer.index')->first()->id;
        Schema::create($module->getTable(), function (Blueprint $table) use($menu) {
            $table->increments('id');
            $table->string('name')->comment('Name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->decimal('sort_order')->default('999');
            $table->boolean('is_default');
            $table->unsignedInteger('menu_id');
            $table->boolean('is_sortable')->default(1);
            $table->foreign('menu_id')
                ->references('id')
                ->on($menu->getTable())
                ->onDelete('CASCADE')
                ->onUpdate('CASCADE');
            $table->unique(['code', 'menu_id']);
            $table->timestamps();
        });

        $columns = [
            [
                'name' => "ID",
                'code' => "id",
                "description" => 'Id',
                "sort_order" => 1,
                "is_sortable" => 1,
                'is_default' => 1,
                "menu_id" => $menuId,
            ],

            [
                'name' => "Name",
                'code' => "first_name",
                "description" => 'Name column',
                "sort_order" => 2,
                "is_sortable" => 1,
                'is_default' => 1,
                "menu_id" => $menuId,
            ],

            [
                'name' => "Profile",
                'code' => "profile_picture",
                "description" => 'Profile picture ',
                "sort_order" => 3,
                "is_sortable" => 1,
                'is_default' => 2,
                "menu_id" => $menuId,
            ],

            [
                'name' => "Email",
                'code' => "email",
                "description" => 'Email ',
                "sort_order" => 4,
                "is_sortable" => 1,
                'is_default' => 1,
                "menu_id" => $menuId,
            ],

            [
                'name' => "Created At",
                'code' => "created_at",
                "description" => 'Created At',
                "sort_order" => 5,
                "is_sortable" => 1,
                'is_default' => 2,
                "menu_id" => $menuId,
            ],
            [
                'name' => "Last Name",
                'code' => "last_name",
                "description" => 'Last Name',
                "sort_order" => 6,
                "is_sortable" => 1,
                'is_default' => 1,
                "menu_id" => $menuId,
            ],
            [
                'name' => "Contact Number",
                'code' => "contact_number",
                "description" => 'Contact Number',
                "sort_order" => 6,
                "is_sortable" => 1,
                'is_default' => 1,
                "menu_id" => $menuId,
            ]
        ];

        DB::table('columns')->insert($columns);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $module = new Column();

        Schema::dropIfExists($module->getTable());

    }
}
