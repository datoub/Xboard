<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMailTouchLogsTable extends Migration
{
    public function up()
    {
        Schema::create('v2_mail_touch_logs', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('rule_key', 64)->index();
            $table->integer('user_id')->nullable()->index();
            $table->string('email', 128);
            $table->string('dedupe_hash', 40);
            $table->text('dedupe_key')->nullable();
            $table->integer('campaign_id')->nullable()->index();
            $table->string('subject');
            $table->string('status', 32)->default('queued')->index();
            $table->integer('created_at');
            $table->integer('updated_at');
            $table->unique(['rule_key', 'email', 'dedupe_hash'], 'mail_touch_rule_email_hash_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('v2_mail_touch_logs');
    }
}
