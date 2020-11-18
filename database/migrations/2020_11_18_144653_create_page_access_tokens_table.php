<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePageAccessTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('page_access_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->index('INDEX_COMPANY_ID');
            $table->string('fb_page_id')->index('INDEX_FB_PAGE_ID');
            $table->bigInteger('user_id');
            $table->string('fb_name', 500);
            $table->string('fb_access_token');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('page_access_tokens');
    }
}
