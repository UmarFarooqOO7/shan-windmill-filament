<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Status;
use App\Models\Lead;

return new class extends Migration
{
    public function up(): void
    {
        // Add new status ID columns
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable()->after('status');
            $table->foreignId('setout_id')->nullable()->after('setout');
            $table->foreignId('writ_id')->nullable()->after('writ');
        });

        // Migrate existing status data
        $leads = Lead::all();
        foreach ($leads as $lead) {
            if ($lead->status) {
                $statusId = Status::where('name', $lead->status)
                    ->where('type', 'lead')
                    ->first()?->id;
                if ($statusId) {
                    $lead->status_id = $statusId;
                }
            }

            if ($lead->setout) {
                $setoutId = Status::where('name', $lead->setout)
                    ->where('type', 'setout')
                    ->first()?->id;
                if ($setoutId) {
                    $lead->setout_id = $setoutId;
                }
            }

            if ($lead->writ) {
                $writId = Status::where('name', $lead->writ)
                    ->where('type', 'writ')
                    ->first()?->id;
                if ($writId) {
                    $lead->writ_id = $writId;
                }
            }

            $lead->save();
        }

        // Remove old status columns
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['status', 'setout', 'writ']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('status')->nullable()->after('status_id');
            $table->string('setout')->nullable()->after('setout_id');
            $table->string('writ')->nullable()->after('writ_id');
        });

        // Restore old status data
        $leads = Lead::all();
        foreach ($leads as $lead) {
            if ($lead->status_id) {
                $lead->status = Status::find($lead->status_id)?->name;
            }
            if ($lead->setout_id) {
                $lead->setout = Status::find($lead->setout_id)?->name;
            }
            if ($lead->writ_id) {
                $lead->writ = Status::find($lead->writ_id)?->name;
            }
            $lead->save();
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['status_id', 'setout_id', 'writ_id']);
        });
    }
};
