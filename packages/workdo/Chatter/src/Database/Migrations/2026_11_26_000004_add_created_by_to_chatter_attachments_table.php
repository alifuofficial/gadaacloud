<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chatter_attachments')) {
            Schema::table('chatter_attachments', function (Blueprint $table) {
                if (!Schema::hasColumn('chatter_attachments', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }
            });

            if (Schema::hasTable('chatter_messages') && Schema::hasColumn('chatter_attachments', 'created_by')) {
                $rows = DB::table('chatter_attachments')->whereNull('created_by')->get(['id', 'chatter_message_id']);
                foreach ($rows as $row) {
                    $companyId = DB::table('chatter_messages')->where('id', $row->chatter_message_id)->value('created_by');
                    if ($companyId) {
                        DB::table('chatter_attachments')->where('id', $row->id)->update(['created_by' => $companyId]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chatter_attachments') && Schema::hasColumn('chatter_attachments', 'created_by')) {
            Schema::table('chatter_attachments', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }
    }
};
