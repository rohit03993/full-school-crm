<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_whatsapp_messages', function (Blueprint $table): void {
            $table->foreignId('sent_by_user_id')
                ->nullable()
                ->after('student_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('send_actor', 20)
                ->nullable()
                ->after('sent_by_user_id')
                ->index();
        });

        if (Schema::hasTable('whatsapp_campaign_recipients') && Schema::hasTable('whatsapp_campaigns')) {
            $rows = DB::table('meta_whatsapp_messages as m')
                ->join('whatsapp_campaign_recipients as r', 'r.id', '=', 'm.whatsapp_campaign_recipient_id')
                ->join('whatsapp_campaigns as c', 'c.id', '=', 'r.whatsapp_campaign_id')
                ->where('m.direction', 'outbound')
                ->whereNull('m.sent_by_user_id')
                ->where(function ($query): void {
                    $query->whereNotNull('c.shot_by')->orWhereNotNull('c.created_by');
                })
                ->select([
                    'm.id',
                    DB::raw('COALESCE(c.shot_by, c.created_by) as sender_id'),
                    'c.campaign_variables',
                ])
                ->limit(5000)
                ->get();

            foreach ($rows as $row) {
                if ($this->isAutomaticAudience($row->campaign_variables ?? null)) {
                    DB::table('meta_whatsapp_messages')
                        ->where('id', $row->id)
                        ->update([
                            'sent_by_user_id' => null,
                            'send_actor' => 'automatic',
                        ]);

                    continue;
                }

                if (! $row->sender_id) {
                    continue;
                }

                DB::table('meta_whatsapp_messages')
                    ->where('id', $row->id)
                    ->update([
                        'sent_by_user_id' => $row->sender_id,
                        'send_actor' => 'staff',
                    ]);
            }
        }

        DB::table('meta_whatsapp_messages')
            ->where('direction', 'outbound')
            ->whereIn('message_source', ['punch', 'homework', 'automation'])
            ->update([
                'send_actor' => 'automatic',
                'sent_by_user_id' => null,
            ]);

        DB::table('meta_whatsapp_messages')
            ->where('direction', 'outbound')
            ->whereNull('send_actor')
            ->whereNotNull('sent_by_user_id')
            ->update(['send_actor' => 'staff']);
    }

    public function down(): void
    {
        Schema::table('meta_whatsapp_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sent_by_user_id');
            $table->dropColumn('send_actor');
        });
    }

    protected function isAutomaticAudience(mixed $campaignVariables): bool
    {
        if (is_string($campaignVariables)) {
            $decoded = json_decode($campaignVariables, true);
            $campaignVariables = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($campaignVariables)) {
            return false;
        }

        $audience = (string) ($campaignVariables['audience_source'] ?? '');

        return in_array($audience, [
            'punch_manual',
            'punch_biometric',
            'attendance',
            'homework_check',
            'fee_reminder',
            'activity_marks',
        ], true);
    }
};
