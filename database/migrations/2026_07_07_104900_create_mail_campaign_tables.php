<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMailCampaignTables extends Migration
{
    public function up()
    {
        Schema::create('v2_mail_campaigns', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('subject');
            $table->longText('content');
            $table->string('scope_type', 32)->default('all');
            $table->longText('scope_payload')->nullable();
            $table->integer('total_count')->default(0);
            $table->integer('queued_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('rate_limit_per_hour')->default(1800);
            $table->integer('retry_limit')->default(3);
            $table->string('status', 32)->default('pending')->index();
            $table->integer('created_by')->nullable();
            $table->integer('started_at')->nullable();
            $table->integer('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_mail_campaign_recipients', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('campaign_id')->index();
            $table->integer('user_id')->nullable()->index();
            $table->string('email', 128);
            $table->longText('vars')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->integer('attempts')->default(0);
            $table->integer('next_attempt_at')->nullable()->index();
            $table->integer('locked_at')->nullable()->index();
            $table->string('locked_by', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->integer('sent_at')->nullable()->index();
            $table->integer('created_at');
            $table->integer('updated_at');
            $table->unique(['campaign_id', 'email'], 'mail_campaign_email_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('v2_mail_campaign_recipients');
        Schema::dropIfExists('v2_mail_campaigns');
    }
}
